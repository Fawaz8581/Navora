<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessProfileController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $profile = $this->profileFor($request);

        if ($profile->completed_at) {
            return redirect()->route('business-profile.show');
        }

        return redirect()->route('onboarding.step', ['step' => $this->currentStep($profile)]);
    }

    public function show(Request $request, int $step): View|RedirectResponse
    {
        abort_if($step < 1 || $step > 3, 404);

        $profile = $this->profileFor($request);

        $currentStep = $this->currentStep($profile);

        if (! $profile->completed_at && $step > $currentStep) {
            return redirect()->route('onboarding.step', ['step' => $currentStep]);
        }

        return view('onboarding.show', [
            'profile' => $profile,
            'step' => $step,
            'isCompleted' => (bool) $profile->completed_at,
        ]);
    }

    public function update(Request $request, int $step): RedirectResponse
    {
        abort_if($step < 1 || $step > 3, 404);

        $profile = $this->profileFor($request);

        $currentStep = $this->currentStep($profile);

        if (! $profile->completed_at && $step > $currentStep) {
            return redirect()->route('onboarding.step', ['step' => $currentStep]);
        }

        if ($step === 1) {
            $validated = $request->validate([
                'business_name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'],
                'category' => ['required', 'string', 'max:255'],
                'location' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
            ]);

            $profile->fill($validated);
            $this->invalidateVendorVerificationIfDetailsChanged($profile, $request->user()->role);
        } elseif ($step === 2) {
            $validated = $request->validate($this->detailsRules($request->user()->role));

            $profile->fill($validated);
            $this->invalidateVendorVerificationIfDetailsChanged($profile, $request->user()->role);
        } else {
            $request->validate([
                'confirm_profile' => ['accepted'],
            ], [
                'confirm_profile.accepted' => 'Konfirmasi data profil sebelum menyelesaikan onboarding.',
            ]);

            $profile->completed_at = now();
        }

        $profile->onboarding_step = max($this->currentStep($profile), min($step + 1, 3));
        $profile->save();

        if ($profile->completed_at) {
            return redirect()
                ->route('business-profile.show')
                ->with('status', 'Profil usaha berhasil disimpan.');
        }

        return redirect()
            ->route('onboarding.step', ['step' => $profile->onboarding_step])
            ->with('status', 'Perubahan profil berhasil disimpan.');
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $profile = $this->profileFor($request);

        if (! $profile->completed_at) {
            return redirect()->route('onboarding.index');
        }

        return view('onboarding.show', [
            'profile' => $profile,
            'step' => 3,
            'isCompleted' => true,
        ]);
    }

    private function profileFor(Request $request): BusinessProfile
    {
        abort_unless(in_array($request->user()->role, ['umkm', 'vendor'], true), 403);

        return $request->user()->businessProfile()->firstOrCreate();
    }

    private function currentStep(BusinessProfile $profile): int
    {
        return max(1, min((int) $profile->onboarding_step, 3));
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
