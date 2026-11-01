<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BusinessProfileController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $profile = $this->profileFor($request);

        if ($profile->completed_at) {
            return redirect()->route('business-profile.show');
        }

        return view('onboarding.show', [
            'profile' => $profile,
            'isCompleted' => false,
            'isEditing' => true,
            'role' => $request->user()->role,
        ]);
    }

    public function show(Request $request, int $step): View|RedirectResponse
    {
        abort_if($step < 1 || $step > 3, 404);

        $profile = $this->profileFor($request);

        if (! $profile->completed_at) {
            return redirect()->route('onboarding.index');
        }

        return view('onboarding.show', [
            'profile' => $profile,
            'isCompleted' => (bool) $profile->completed_at,
            'isEditing' => true,
            'role' => $request->user()->role,
        ]);
    }

    public function update(Request $request, int $step): RedirectResponse
    {
        abort_if($step < 1 || $step > 3, 404);

        $profile = $this->profileFor($request);
        $validated = $request->validate(array_merge([
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'confirm_profile' => ['accepted'],
        ], $this->detailsRules($request->user()->role)), [
            'confirm_profile.accepted' => 'Tinjau dan konfirmasi profil sebelum menyimpannya.',
            'profile_image.image' => 'Foto profil harus berupa file gambar yang valid.',
            'profile_image.mimes' => 'Foto profil harus berformat JPG, PNG, GIF, atau WebP.',
            'profile_image.max' => 'Ukuran foto profil maksimal 5 MB.',
            'cover_image.image' => 'Foto cover harus berupa file gambar yang valid.',
            'cover_image.mimes' => 'Foto cover harus berformat JPG, PNG, GIF, atau WebP.',
            'cover_image.max' => 'Ukuran foto cover maksimal 5 MB.',
        ]);

        $replacedPaths = [];
        $storedPaths = [];

        try {
            foreach (['profile_image' => 'profile', 'cover_image' => 'cover'] as $field => $directory) {
                if (! $request->hasFile($field)) {
                    continue;
                }

                $path = $request->file($field)->store('business-profiles/'.$profile->id.'/'.$directory, 'local');
                if ($path === false) {
                    throw new RuntimeException('Gambar profil gagal disimpan ke penyimpanan.');
                }

                $storedPaths[] = $path;
                $attribute = $field.'_path';
                if ($profile->{$attribute}) {
                    $replacedPaths[] = $profile->{$attribute};
                }
                $profile->{$attribute} = $path;
            }

            $profile->fill(Arr::except($validated, ['confirm_profile', 'profile_image', 'cover_image']));
            $this->invalidateVendorVerificationIfDetailsChanged($profile, $request->user()->role);
            $profile->completed_at = $profile->completed_at ?? now();
            $profile->onboarding_step = 3;
            $profile->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        foreach ($replacedPaths as $path) {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('A replaced business profile image could not be deleted from storage.', [
                    'profile_id' => $profile->id,
                    'path' => $path,
                ]);
            }
        }

        return redirect()
            ->route('business-profile.show')
            ->with('status', 'Profil usaha berhasil disimpan.');
    }

    public function showImage(Request $request, BusinessProfile $businessProfile, string $type): BinaryFileResponse
    {
        abort_unless(in_array($type, ['profile', 'cover'], true), 404);

        $user = $request->user();
        $isOwner = in_array($user->role, ['umkm', 'vendor'], true)
            && $user->businessProfile?->is($businessProfile);
        $isCatalogBuyer = $user->role === 'umkm'
            && $businessProfile->user?->role === 'vendor'
            && $businessProfile->verification_status === 'approved'
            && $businessProfile->completed_at !== null;

        abort_unless($isOwner || $isCatalogBuyer, 404);

        $path = $businessProfile->{$type.'_image_path'};
        $expectedDirectory = 'business-profiles/'.$businessProfile->id.'/'.$type.'/';
        abort_unless(is_string($path) && str_starts_with($path, $expectedDirectory), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $profile = $this->profileFor($request);

        if (! $profile->completed_at) {
            return redirect()->route('onboarding.index');
        }

        return view('onboarding.show', [
            'profile' => $profile,
            'isCompleted' => true,
            'isEditing' => false,
            'role' => $request->user()->role,
        ]);
    }

    private function profileFor(Request $request): BusinessProfile
    {
        abort_unless(in_array($request->user()->role, ['umkm', 'vendor'], true), 403);

        return $request->user()->businessProfile()->firstOrCreate();
    }

    private function invalidateVendorVerificationIfDetailsChanged(BusinessProfile $profile, string $role): void
    {
        if ($role !== 'vendor' || ! in_array($profile->verification_status, ['pending', 'approved'], true)) {
            return;
        }

        if (! $profile->isDirty([
            'business_name',
            'phone',
            'category',
            'location',
            'description',
            'product_categories',
            'materials',
            'minimum_order_quantity',
            'monthly_capacity',
            'production_lead_time_days',
            'indicative_price_per_unit',
            'latitude',
            'longitude',
        ])) {
            return;
        }

        $profile->forceFill([
            'verification_status' => 'not_submitted',
            'verification_notes' => null,
            'verification_submitted_at' => null,
            'verification_reviewed_at' => null,
            'verified_by' => null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function detailsRules(string $role): array
    {
        if ($role === 'vendor') {
            return [
                'product_categories' => ['required', 'string', 'max:255'],
                'materials' => ['nullable', 'string', 'max:255'],
                'minimum_order_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
                'monthly_capacity' => ['nullable', 'integer', 'min:1', 'max:100000000'],
                'production_lead_time_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                'indicative_price_per_unit' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ];
        }

        return [
            'product_type' => ['required', 'string', 'max:255'],
            'packaging_type' => ['nullable', 'string', 'max:255'],
            'preferred_material' => ['nullable', 'string', 'max:255'],
            'monthly_quantity' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
