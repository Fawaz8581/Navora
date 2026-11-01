<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\OrderParticipant;
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

        $campaign = AdCampaign::query()->where('order_id', $validated['order_id'])->first();

        if ($campaign) {
            abort_unless($this->amountMatches($validated['gross_amount'], $campaign->amount), 422);

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

        $order = Order::query()->where('payment_order_id', $validated['order_id'])->first();

        if ($order) {
            abort_unless($this->amountMatches($validated['gross_amount'], (int) $order->total_amount), 422);

            DB::transaction(function () use ($order, $validated): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                if ($lockedOrder->paid_at) {
                    return;
                }

                $outcome = $this->paymentOutcome($validated);

                if ($outcome === 'paid') {
                    $lockedOrder->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                } elseif ($outcome === 'failed') {
                    $lockedOrder->forceFill(['payment_status' => 'failed'])->save();
                }
            });

            return response()->json(['status' => 'ok']);
        }

        $participant = OrderParticipant::query()
            ->where('payment_order_id', $validated['order_id'])
            ->firstOrFail();
        abort_unless($this->amountMatches($validated['gross_amount'], (int) $participant->payment_amount), 422);

        DB::transaction(function () use ($participant, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($participant->order_id);
            $lockedParticipant = OrderParticipant::query()->lockForUpdate()->findOrFail($participant->id);

            if ($lockedParticipant->paid_at) {
                return;
            }

            $outcome = $this->paymentOutcome($validated);

            if ($outcome === 'paid') {
                $lockedParticipant->forceFill([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ])->save();

                if (! $lockedOrder->participants()->where('payment_status', '!=', 'paid')->exists()) {
                    $lockedOrder->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                }
            } elseif ($outcome === 'failed') {
                $lockedParticipant->forceFill(['payment_status' => 'failed'])->save();
            }
        });

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array{transaction_status: string, fraud_status?: ?string}  $notification
     */
    private function paymentOutcome(array $notification): ?string
    {
        $transactionStatus = $notification['transaction_status'];

        if ($transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && ($notification['fraud_status'] ?? null) === 'accept')) {
            return 'paid';
        }

        if (in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)
            || ($transactionStatus === 'capture' && ($notification['fraud_status'] ?? null) === 'deny')) {
            return 'failed';
        }

        return null;
    }

    private function amountMatches(mixed $grossAmount, int $expected): bool
    {
        $grossAmount = (string) $grossAmount;

        if (preg_match('/^\d+(?:\.\d+)?$/D', $grossAmount) !== 1) {
            return false;
        }

        [$wholeAmount, $fractionalAmount] = array_pad(explode('.', $grossAmount, 2), 2, '');

        return (int) $wholeAmount === $expected
            && trim($fractionalAmount, '0') === '';
    }
}
