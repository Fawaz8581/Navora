<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);

        return response()->json([
            'verified' => $this->isVerified($vendor),
            'listings' => $vendor->vendorListings()
                ->latest('id')
                ->get()
                ->map(fn (VendorListing $listing): array => $this->presentListing($listing))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);
        abort_unless($this->isVerified($vendor), 403, 'Profil vendor harus disetujui sebelum katalog diajukan.');

        $validated = $request->validate([
            'client_reference' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['packaging', 'printing', 'maklon'])],
            'material' => ['required', 'string', 'max:100'],
            'minimum_order_quantity' => ['required', 'integer', 'between:1,100000000'],
            'description' => ['required', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama layanan atau produk wajib diisi.',
            'minimum_order_quantity.required' => 'MOQ wajib diisi.',
            'minimum_order_quantity.between' => 'MOQ harus antara 1 dan 100.000.000 pcs.',
        ]);

        $listing = $vendor->vendorListings()->firstOrNew([
            'client_reference' => $validated['client_reference'],
        ]);
        $contentChanged = ! $listing->exists
            || collect([
                'name',
                'category',
                'material',
                'minimum_order_quantity',
                'description',
            ])->contains(fn (string $attribute): bool => $listing->{$attribute} !== $validated[$attribute]);

        $listing->fill($validated);

        if ($contentChanged || $listing->status === VendorListing::STATUS_DRAFT) {
            $listing->status = VendorListing::STATUS_PENDING;
            $listing->approved_by = null;
            $listing->approved_at = null;
        }

        $listing->save();

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
     * @return array<string, int|string>
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
            'description' => $listing->description,
            'status' => $listing->status,
        ];
    }
}
