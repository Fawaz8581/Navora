<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:50'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'numeric'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'fraud_status' => ['nullable', 'string'],
        ]);

        $serverKey = config('services.midtrans.server_key');
        abort_unless(is_string($serverKey) && $serverKey !== '', 503);

        $expectedSignature = hash('sha512', $validated['order_id'].$validated['status_code'].$validated['gross_amount'].$serverKey);
        abort_unless(hash_equals($expectedSignature, $validated['signature_key']), 403);

        $campaign = AdCampaign::query()->where('order_id', $validated['order_id'])->firstOrFail();
        abort_unless(
            number_format((float) $validated['gross_amount'], 2, '.', '') === number_format($campaign->amount, 2, '.', ''),
            422,
        );

        DB::transaction(function () use ($campaign, $validated): void {
            $lockedCampaign = AdCampaign::query()->lockForUpdate()->findOrFail($campaign->id);

            if ($lockedCampaign->paid_at) {
                return;
            }

            $transactionStatus = $validated['transaction_status'];
            $paid = $transactionStatus === 'settlement'
                || ($transactionStatus === 'capture' && ($validated['fraud_status'] ?? null) === 'accept');

            if ($paid) {
                $lockedCampaign->forceFill([
                    'payment_status' => 'paid',
                    'status' => AdCampaign::STATUS_PAID,
                    'paid_at' => now(),
                ])->save();

                return;
            }

            $failed = in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)
                || ($transactionStatus === 'capture' && ($validated['fraud_status'] ?? null) === 'deny');

            if ($failed) {
                $lockedCampaign->forceFill([
                    'payment_status' => $transactionStatus === 'capture' ? 'fraud_denied' : $transactionStatus,
                    'status' => AdCampaign::STATUS_PAYMENT_FAILED,
                ])->save();
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
