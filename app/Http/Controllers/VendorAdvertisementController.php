<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdPackage;
use App\Services\MidtransSnapService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class VendorAdvertisementController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->ensureVerifiedVendor($request);

        return redirect()->route('workspace', ['page' => 'advertisements']);
    }

    /**
     * @return array{
     *     advertisementPackages: Collection<int, AdPackage>,
     *     vendorAdCampaigns: Collection<int, AdCampaign>,
     *     midtransConfigured: bool,
     *     midtransClientKey: mixed,
     *     midtransSnapUrl: string
     * }
     */
    public function workspaceData(Request $request): array
    {
        $this->ensureVerifiedVendor($request);

        return [
            'advertisementPackages' => AdPackage::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
            'vendorAdCampaigns' => AdCampaign::query()
                ->whereBelongsTo($request->user(), 'vendor')
                ->latest()
                ->get(),
            'midtransConfigured' => $this->midtransIsConfigured(),
            'midtransClientKey' => config('services.midtrans.client_key'),
            'midtransSnapUrl' => rtrim((string) config('services.midtrans.base_url'), '/').'/snap/snap.js',
        ];
    }

    public function store(Request $request, MidtransSnapService $midtrans): RedirectResponse|JsonResponse
    {
        $this->ensureVerifiedVendor($request);

        $validated = $request->validate([
            'ad_package_id' => [
                'required',
                'integer',
                Rule::exists('ad_packages', 'id')->where('is_active', true),
            ],
            'ad_title' => ['required', 'string', 'max:120'],
            'destination_url' => ['required', 'url:https', 'max:2048'],
            'asset' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp,bmp,avif', 'extensions:jpg,jpeg,png,gif,webp,bmp,avif', 'max:5120'],
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
        $dimensions = AdCampaign::dimensionsForPlacement($package->placement);
        $campaign = AdCampaign::query()->create([
            'ad_package_id' => $package->id,
            'vendor_user_id' => $vendor->id,
            'package_name' => $package->name,
            'ad_title' => $validated['ad_title'],
            'destination_url' => $validated['destination_url'],
            'placement' => $package->placement,
            ...$dimensions,
            'amount' => AdCampaign::priceForPlacement($package->placement),
            'duration_days' => $package->duration_days,
            'order_id' => 'NAVORA-AD-'.Str::uuid(),
            'payment_status' => 'pending',
            'status' => AdCampaign::STATUS_PENDING_PAYMENT,
        ]);
        $assetPath = $validated['asset']->store('advertisements/'.$campaign->id, 'local');

        if ($assetPath === false) {
            throw new RuntimeException('Aset iklan tidak berhasil disimpan.');
        }

        $campaign->forceFill([
            'asset_path' => $assetPath,
            'asset_submitted_at' => now(),
        ])->save();

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

        if ($request->expectsJson()) {
            return response()->json([
                'token' => $checkout['token'],
                'campaign_id' => $campaign->id,
                'status_url' => route('vendor.advertisements.refresh', $campaign),
            ]);
        }

        return redirect()
            ->route('workspace', ['page' => 'advertisements'])
            ->with('snapToken', $checkout['token'])
            ->with('campaignId', $campaign->id)
            ->with('statusUrl', route('vendor.advertisements.refresh', $campaign));
    }

    public function uploadAsset(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        $this->ensureVerifiedVendor($request);
        abort_unless($adCampaign->vendor_user_id === $request->user()->id, 404);
        abort_unless($adCampaign->status === AdCampaign::STATUS_AWAITING_ASSET, 409);

        $validated = $request->validate([
            'asset' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp,bmp,avif', 'extensions:jpg,jpeg,png,gif,webp,bmp,avif', 'max:5120'],
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
            ->route('workspace', ['page' => 'advertisements'])
            ->with('status', 'Aset iklan berhasil dikirim dan sedang menunggu pemeriksaan admin.');
    }

    public function updateCampaign(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        $this->ensureVerifiedVendor($request);
        abort_unless($adCampaign->vendor_user_id === $request->user()->id, 404);
        abort_unless(
            in_array($adCampaign->status, [
                AdCampaign::STATUS_ASSET_SUBMITTED,
                AdCampaign::STATUS_ACTIVE,
            ], true)
                && $adCampaign->payment_status === 'paid',
            409,
        );

        $validated = $request->validate([
            'ad_title' => ['required', 'string', 'max:120'],
            'destination_url' => ['required', 'url:https', 'max:2048'],
            'asset' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,bmp,avif', 'extensions:jpg,jpeg,png,gif,webp,bmp,avif', 'max:5120'],
        ]);

        $assetPath = $adCampaign->asset_path;
        if ($validated['asset'] ?? null) {
            $assetPath = $validated['asset']->store('advertisements/'.$adCampaign->id, 'local');

            if ($assetPath === false) {
                throw new RuntimeException('Aset iklan tidak berhasil disimpan.');
            }
        }

        $wasActive = $adCampaign->status === AdCampaign::STATUS_ACTIVE;
        $adCampaign->forceFill([
            'ad_title' => $validated['ad_title'],
            'destination_url' => $validated['destination_url'],
            'asset_path' => $assetPath,
            'asset_submitted_at' => now(),
            ...($wasActive ? [
                'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
                'approved_by' => null,
                'approved_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_reason' => null,
                'published_by' => null,
                'published_at' => null,
                'starts_at' => null,
                'ends_at' => null,
            ] : []),
        ])->save();

        return redirect()
            ->route('workspace', ['page' => 'advertisements'])
            ->with(
                'status',
                $wasActive
                    ? 'Iklan berhasil diperbarui dan dikirim kembali untuk ditinjau admin.'
                    : 'Iklan berhasil diperbarui.',
            );
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
        $clientKey = config('services.midtrans.client_key');

        return is_string($serverKey)
            && trim($serverKey) !== ''
            && is_string($clientKey)
            && trim($clientKey) !== '';
    }
}
