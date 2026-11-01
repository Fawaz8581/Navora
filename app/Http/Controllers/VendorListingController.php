<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorListing;
use App\Services\VideoDurationInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class VendorListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        return response()->json([
            'verified' => $this->isVerified($vendor),
            'listings' => $vendor->vendorListings()
                ->with('media')
                ->latest('id')
                ->get()
                ->map(fn (VendorListing $listing): array => $this->presentListing($listing))
                ->values(),
        ]);
    }

    public function store(Request $request, VideoDurationInspector $videoDurationInspector): JsonResponse
    {
        $vendor = $this->vendor($request);
        abort_unless($this->isVerified($vendor), 403, 'Profil vendor harus disetujui sebelum katalog diajukan.');

        $validated = $request->validate([
            'client_reference' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['packaging', 'printing', 'maklon'])],
            'material' => ['required', 'string', 'max:100'],
            'minimum_order_quantity' => ['required', 'integer', 'between:1,100000000'],
            'pricing_type' => ['required', Rule::in([
                VendorListing::PRICING_FINAL,
                VendorListing::PRICING_NEGOTIABLE,
            ])],
            'price_per_unit' => [
                Rule::requiredIf($request->input('pricing_type') === VendorListing::PRICING_FINAL),
                'nullable',
                'integer',
                'between:1,1000000000',
            ],
            'description' => ['required', 'string', 'max:2000'],
            'remove_media_ids' => ['sometimes', 'array', 'max:4'],
            'remove_media_ids.*' => ['integer', 'distinct'],
            'media' => ['sometimes', 'array', 'max:4'],
            'media.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm',
                'max:10240',
            ],
        ], [
            'name.required' => 'Nama layanan atau produk wajib diisi.',
            'minimum_order_quantity.required' => 'MOQ wajib diisi.',
            'minimum_order_quantity.between' => 'MOQ harus antara 1 dan 100.000.000 pcs.',
            'price_per_unit.required' => 'Harga final per pcs wajib diisi.',
            'price_per_unit.between' => 'Harga per pcs harus antara Rp1 dan Rp1.000.000.000.',
            'price_per_unit.integer' => 'Harga per pcs harus berupa bilangan bulat rupiah.',
            'remove_media_ids.max' => 'Maksimal 4 lampiran dapat dihapus sekaligus.',
            'media.max' => 'Maksimal 4 file gambar, GIF, atau video dapat ditambahkan ke satu katalog.',
            'media.*.mimetypes' => 'Media harus berupa JPG, PNG, GIF, WebP, MP4, atau WebM.',
            'media.*.max' => 'Ukuran setiap file media maksimal 10 MB.',
        ]);

        $mediaFiles = $validated['media'] ?? [];
        $removeMediaIds = $validated['remove_media_ids'] ?? [];
        unset($validated['media']);
        unset($validated['remove_media_ids']);

        foreach ($mediaFiles as $mediaFile) {
            if (! str_starts_with((string) $mediaFile->getMimeType(), 'video/')) {
                continue;
            }

            $duration = $videoDurationInspector->duration($mediaFile->getPathname());

            if ($duration === null || $duration <= 0 || $duration > 5) {
                throw ValidationException::withMessages([
                    'media' => 'Setiap video produk harus memiliki durasi lebih dari 0 dan maksimal 5 detik.',
                ]);
            }
        }

        $storedPaths = [];
        $removedMediaPaths = [];

        try {
            $listing = DB::transaction(function () use ($vendor, $validated, $mediaFiles, $removeMediaIds, &$storedPaths, &$removedMediaPaths): VendorListing {
                $listing = $vendor->vendorListings()
                    ->where('client_reference', $validated['client_reference'])
                    ->lockForUpdate()
                    ->first();

                if (! $listing) {
                    $listing = $vendor->vendorListings()->make();
                }

                $mediaToRemove = $listing->media()
                    ->whereIn('id', $removeMediaIds)
                    ->get();

                if ($mediaToRemove->count() !== count($removeMediaIds)) {
                    throw ValidationException::withMessages([
                        'remove_media_ids' => 'Lampiran yang dipilih tidak ditemukan pada katalog ini.',
                    ]);
                }

                $remainingMediaCount = $listing->media()->count() - $mediaToRemove->count();
                if ($remainingMediaCount + count($mediaFiles) > 4) {
                    throw ValidationException::withMessages([
                        'media' => 'Katalog hanya dapat memiliki maksimal 4 lampiran. Hapus lampiran lama atau kurangi file baru.',
                    ]);
                }

                $contentChanged = ! $listing->exists
                    || collect([
                        'name',
                        'category',
                        'material',
                        'minimum_order_quantity',
                        'pricing_type',
                        'price_per_unit',
                        'description',
                    ])->contains(fn (string $attribute): bool => $listing->{$attribute} !== $validated[$attribute])
                    || $mediaFiles !== []
                    || $mediaToRemove->isNotEmpty();
                $isPublished = $listing->exists && $listing->status === VendorListing::STATUS_ACTIVE;

                $listing->fill($validated);

                if (! $isPublished && ($contentChanged || $listing->status === VendorListing::STATUS_DRAFT)) {
                    $listing->status = VendorListing::STATUS_PENDING;
                    $listing->approved_by = null;
                    $listing->approved_at = null;
                }

                $listing->save();

                if ($mediaToRemove->isNotEmpty()) {
                    $removedMediaPaths = $mediaToRemove->pluck('path')->all();
                    $listing->media()->whereKey($mediaToRemove->modelKeys())->delete();
                }

                $startingPosition = $listing->media()->count();

                foreach ($mediaFiles as $position => $mediaFile) {
                    $path = $mediaFile->store('vendor-catalog/listings/'.$listing->id, 'local');

                    if ($path === false) {
                        throw new RuntimeException('Media katalog gagal disimpan ke penyimpanan.');
                    }

                    $storedPaths[] = $path;
                    $mimeType = (string) $mediaFile->getMimeType();
                    $listing->media()->create([
                        'path' => $path,
                        'mime_type' => $mimeType,
                        'media_type' => str_starts_with($mimeType, 'video/') ? 'video' : 'image',
                        'position' => $startingPosition + $position,
                    ]);
                }

                return $listing->load('media');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        foreach ($removedMediaPaths as $path) {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('A removed vendor listing attachment could not be deleted from storage.', [
                    'listing_id' => $listing->id,
                    'path' => $path,
                ]);
            }
        }

        return response()->json([
            'listing' => $this->presentListing($listing),
        ], 201);
    }

    private function vendor(Request $request): User
    {
        abort_unless($request->user()->role === 'vendor', 403);

        return $request->user();
    }

    private function isVerified(User $vendor): bool
    {
        $profile = $vendor->businessProfile;

        return $profile?->completed_at !== null && $profile->verification_status === 'approved';
    }

    /**
     * @return array<string, int|string|null|array<int, array<string, int|string>>>
     */
    private function presentListing(VendorListing $listing): array
    {
        return [
            'id' => $listing->id,
            'client_reference' => $listing->client_reference,
            'name' => $listing->name,
            'category' => $listing->category,
            'material' => $listing->material,
            'minimum_order_quantity' => $listing->minimum_order_quantity,
            'price_per_unit' => $listing->price_per_unit,
            'pricing_type' => $listing->pricing_type,
            'description' => $listing->description,
            'status' => $listing->status,
            'media' => $listing->media
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'type' => $media->media_type,
                    'mime_type' => $media->mime_type,
                    'url' => route('vendors.catalog.listing-media', [$listing, $media]),
                ])
                ->all(),
        ];
    }
}
