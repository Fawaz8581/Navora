<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdPackage;
use App\Services\MidtransSnapService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class VendorAdvertisementController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->ensureVerifiedVendor($request);

        return view('vendor.advertisements.index', [
            'packages' => AdPackage::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
            'campaigns' => AdCampaign::query()
                ->whereBelongsTo($request->user(), 'vendor')
                ->latest()
                ->get(),
            'midtransConfigured' => $this->midtransIsConfigured(),
        ]);
    }

    public function store(Request $request, MidtransSnapService $midtrans): RedirectResponse
    {
        $this->ensureVerifiedVendor($request);

        $validated = $request->validate([
            'ad_package_id' => [
                'required',
                'integer',
                Rule::exists('ad_packages', 'id')->where('is_active', true),
            ],
        ]);

        $package = AdPackage::query()
            ->whereKey($validated['ad_package_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $this->midtransIsConfigured()) {
            return back()
                ->withErrors(['payment' => 'Pembayaran Midtrans belum dikonfigurasi admin. Silakan hubungi admin Navora.'])
                ->withInput();
        }

        $vendor = $request->user();
        $campaign = AdCampaign::query()->create([
            'ad_package_id' => $package->id,
            'vendor_user_id' => $vendor->id,
            'package_name' => $package->name,
            'placement' => $package->placement,
            'width' => $package->width,
            'height' => $package->height,
            'amount' => $package->price,
            'duration_days' => $package->duration_days,
            'order_id' => 'NAVORA-AD-'.Str::uuid(),
            'payment_status' => 'pending',
            'status' => AdCampaign::STATUS_PENDING_PAYMENT,
        ]);

        try {
            $checkout = $midtrans->createTransaction($campaign, $vendor);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans advertisement checkout could not be created.', [
                'campaign_id' => $campaign->id,
                'exception' => $exception->getMessage(),
            ]);

            $campaign->forceFill([
                'payment_status' => 'initiation_failed',
                'status' => AdCampaign::STATUS_PAYMENT_FAILED,
            ])->save();

            return back()
                ->withErrors(['payment' => 'Checkout belum berhasil dibuat. Silakan coba lagi atau hubungi admin.'])
                ->withInput();
        }

        $campaign->forceFill([
            'snap_token' => $checkout['token'],
            'snap_redirect_url' => $checkout['redirect_url'],
        ])->save();

        return redirect()->away($checkout['redirect_url']);
    }

    public function uploadAsset(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        $this->ensureVerifiedVendor($request);
        abort_unless($adCampaign->vendor_user_id === $request->user()->id, 404);
        abort_unless($adCampaign->status === AdCampaign::STATUS_AWAITING_ASSET, 409);

        $validated = $request->validate([
            'asset' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $assetPath = $validated['asset']->store('advertisements/'.$adCampaign->id, 'local');

        if ($assetPath === false) {
            throw new RuntimeException('Aset iklan tidak berhasil disimpan.');
        }

        $adCampaign->forceFill([
            'asset_path' => $assetPath,
            'asset_submitted_at' => now(),
            'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        ])->save();

        return redirect()
            ->route('vendor.advertisements.index')
            ->with('status', 'Aset iklan berhasil dikirim dan sedang menunggu pemeriksaan admin.');
    }

    private function ensureVerifiedVendor(Request $request): void
    {
        abort_unless($request->user()->role === 'vendor', 403);
        $profile = $request->user()->businessProfile;

        abort_unless($profile?->completed_at && $profile->verification_status === 'approved', 403);
    }

    private function midtransIsConfigured(): bool
    {
        $serverKey = config('services.midtrans.server_key');

        return is_string($serverKey) && trim($serverKey) !== '';
    }
}
