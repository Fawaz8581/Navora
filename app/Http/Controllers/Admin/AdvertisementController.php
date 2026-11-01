<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdPlacementSetting;
use App\Services\MidtransSnapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AdvertisementController extends Controller
{
    public function index(): View
    {
        return view('admin.customize', [
            'campaigns' => AdCampaign::query()
                ->with(['vendor:id,name,email', 'reviewedBy:id,name'])
                ->latest()
                ->paginate(20),
            'verifiedIncome' => AdCampaign::query()
                ->where('payment_status', 'paid')
                ->sum('amount'),
            'slotSettings' => AdPlacementSetting::query()->get()->keyBy('placement'),
            'activeTopCount' => $this->activeCampaigns(['top', 'global'])->count(),
            'activeSidebarCount' => $this->activeCampaigns(['sidebar'])->count(),
        ]);
    }

    public function updateSlotSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'top' => ['required', 'integer', 'min:1', 'max:20'],
            'sidebar' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ($validated as $placement => $maxActiveAds) {
                AdPlacementSetting::query()->updateOrCreate(
                    ['placement' => $placement],
                    ['max_active_ads' => $maxActiveAds],
                );
            }
        });

        return back()->with('status', 'Kapasitas iklan banner atas dan samping berhasil diperbarui.');
    }

    public function reviewCampaign(
        Request $request,
        AdCampaign $adCampaign,
        MidtransSnapService $midtrans,
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ]);

        $adCampaign = DB::transaction(function () use ($adCampaign, $request, $validated): AdCampaign {
            $lockedCampaign = AdCampaign::query()->lockForUpdate()->findOrFail($adCampaign->id);
            abort_unless(
                $lockedCampaign->status === AdCampaign::STATUS_ASSET_SUBMITTED
                    && $lockedCampaign->payment_status === 'paid'
                    && $lockedCampaign->asset_path,
                409,
            );

            $reviewedAt = now();

            if ($validated['decision'] === 'approve') {
                $placementSetting = $lockedCampaign->placement === 'sidebar' ? 'sidebar' : 'top';
                $setting = AdPlacementSetting::query()
                    ->where('placement', $placementSetting)
                    ->lockForUpdate()
                    ->firstOrFail();
                $placements = $placementSetting === 'sidebar' ? ['sidebar'] : ['top', 'global'];

                if ($this->activeCampaigns($placements)->count() >= $setting->max_active_ads) {
                    throw ValidationException::withMessages([
                        'capacity' => 'Kapasitas iklan untuk posisi ini sudah penuh. Naikkan batas slot atau tunggu slot tersedia sebelum menyetujui.',
                    ]);
                }

                $lockedCampaign->forceFill([
                    'status' => AdCampaign::STATUS_ACTIVE,
                    'approved_by' => $request->user()->id,
                    'approved_at' => $reviewedAt,
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $reviewedAt,
                    'review_reason' => $validated['reason'] ?? null,
                    'published_by' => $request->user()->id,
                    'published_at' => $reviewedAt,
                    'starts_at' => $reviewedAt,
                    'ends_at' => $reviewedAt->copy()->addDays($lockedCampaign->duration_days),
                ])->save();

                return $lockedCampaign;
            }

            $lockedCampaign->forceFill([
                'status' => AdCampaign::STATUS_REJECTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $reviewedAt,
                'review_reason' => $validated['reason'],
                'refund_key' => 'NAVORA-AD-REFUND-'.$lockedCampaign->id,
                'refund_status' => 'pending',
            ])->save();

            return $lockedCampaign;
        });

        if ($validated['decision'] === 'approve') {
            return back()->with('status', 'Iklan disetujui dan langsung ditayangkan sesuai posisi yang dipilih.');
        }

        return $this->requestRefund($adCampaign, $validated['reason'], $midtrans);
    }

    public function retryRefund(AdCampaign $adCampaign, MidtransSnapService $midtrans): RedirectResponse
    {
        $adCampaign = DB::transaction(function () use ($adCampaign): AdCampaign {
            $lockedCampaign = AdCampaign::query()->lockForUpdate()->findOrFail($adCampaign->id);
            abort_unless(
                $lockedCampaign->status === AdCampaign::STATUS_REJECTED
                    && $lockedCampaign->refund_status === 'failed'
                    && $lockedCampaign->payment_status === 'paid',
                409,
            );

            $lockedCampaign->forceFill(['refund_status' => 'pending'])->save();

            return $lockedCampaign;
        });

        return $this->requestRefund($adCampaign, (string) $adCampaign->review_reason, $midtrans);
    }

    private function requestRefund(
        AdCampaign $adCampaign,
        string $reason,
        MidtransSnapService $midtrans,
    ): RedirectResponse {
        try {
            $refund = $midtrans->refundAdvertisement($adCampaign, $reason);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans advertisement refund could not be confirmed.', [
                'campaign_id' => $adCampaign->id,
                'exception' => $exception->getMessage(),
            ]);

            $adCampaign->forceFill(['refund_status' => 'failed'])->save();

            return back()->withErrors([
                'refund' => 'Iklan ditolak, tetapi permintaan refund Midtrans gagal. Status dan alasan penolakan sudah tersimpan; admin dapat mencoba refund kembali.',
            ]);
        }

        $cancelled = $refund['transaction_status'] === 'cancel';
        $adCampaign->forceFill([
            'refund_status' => $cancelled ? 'cancelled' : 'requested',
            'payment_status' => $cancelled ? 'cancelled' : $adCampaign->payment_status,
            'refund_requested_at' => now(),
        ])->save();

        return back()->with(
            'status',
            $cancelled
                ? 'Iklan ditolak dengan alasan dan pembayaran berhasil dibatalkan di Midtrans.'
                : 'Iklan ditolak dengan alasan. Permintaan refund otomatis telah diterima Midtrans.',
        );
    }

    /**
     * @param  list<string>  $placements
     */
    private function activeCampaigns(array $placements): Builder
    {
        return AdCampaign::query()
            ->where('status', AdCampaign::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereIn('placement', $placements);
    }
}
