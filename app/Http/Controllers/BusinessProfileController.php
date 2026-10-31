<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

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
            'confirm_profile' => ['accepted'],
        ], $this->detailsRules($request->user()->role)), [
            'confirm_profile.accepted' => 'Tinjau dan konfirmasi profil sebelum menyimpannya.',
        ]);

        $profile->fill(Arr::except($validated, ['confirm_profile']));
        $this->invalidateVendorVerificationIfDetailsChanged($profile, $request->user()->role);
        $profile->completed_at = $profile->completed_at ?? now();
        $profile->onboarding_step = 3;
        $profile->save();

        return redirect()
            ->route('business-profile.show')
            ->with('status', 'Profil usaha berhasil disimpan.');
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
