<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\OrderParticipant;
use App\Services\MidtransSnapService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'payment_type' => ['nullable', 'string', 'max:50'],
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
                $transactionStatus = $validated['transaction_status'];

                $paymentDetails = array_filter([
                    'midtrans_transaction_id' => $validated['transaction_id'] ?? null,
                    'payment_type' => $validated['payment_type'] ?? null,
                    'midtrans_transaction_status' => $transactionStatus,
                ], fn (?string $value): bool => $value !== null && $value !== '');

                if (in_array($transactionStatus, ['refund', 'partial_refund'], true)) {
                    $lockedCampaign->forceFill([
                        ...$paymentDetails,
                        'payment_status' => $transactionStatus === 'refund' ? 'refunded' : 'partially_refunded',
                        'refund_status' => $transactionStatus === 'refund' ? 'refunded' : 'partially_refunded',
                    ])->save();

                    return;
                }

                if ($transactionStatus === 'cancel'
                    && $lockedCampaign->status === AdCampaign::STATUS_REJECTED
                    && in_array($lockedCampaign->refund_status, ['pending', 'cancel_requested', 'requested'], true)) {
                    $lockedCampaign->forceFill([
                        ...$paymentDetails,
                        'payment_status' => 'cancelled',
                        'refund_status' => 'cancelled',
                    ])->save();

                    return;
                }

                if ($lockedCampaign->paid_at) {
                    if (in_array($transactionStatus, ['capture', 'settlement'], true)) {
                        $lockedCampaign->forceFill($paymentDetails)->save();
                    }

                    return;
                }

                $paid = $transactionStatus === 'settlement'
                    || ($transactionStatus === 'capture' && ($validated['fraud_status'] ?? null) === 'accept');

                if ($paid) {
                    $lockedCampaign->forceFill([
                        ...$paymentDetails,
                        'payment_status' => 'paid',
                        'status' => $lockedCampaign->asset_path
                            ? AdCampaign::STATUS_ASSET_SUBMITTED
                            : AdCampaign::STATUS_PAID,
                        'paid_at' => now(),
                    ])->save();

                    return;
                }

                $failed = in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)
                    || ($transactionStatus === 'capture' && ($validated['fraud_status'] ?? null) === 'deny');

                if ($failed) {
                    $lockedCampaign->forceFill([
                        ...$paymentDetails,
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
                $transactionStatus = $validated['transaction_status'];
                $paymentDetails = array_filter([
                    'midtrans_transaction_id' => $validated['transaction_id'] ?? null,
                    'payment_type' => $validated['payment_type'] ?? null,
                    'midtrans_transaction_status' => $transactionStatus,
                ], fn (?string $value): bool => $value !== null && $value !== '');

                if ($lockedOrder->paid_at) {
                    $lockedOrder->forceFill($paymentDetails)->save();

                    return;
                }

                $outcome = $this->paymentOutcome($validated);

                if ($outcome === 'paid') {
                    $lockedOrder->forceFill([
                        ...$paymentDetails,
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                    $lockedOrder->statusHistory()->create([
                        'user_id' => null,
                        'status' => $lockedOrder->status,
                        'note' => 'Pembayaran berhasil diverifikasi oleh Midtrans.',
                    ]);
                } elseif ($outcome === 'failed') {
                    $lockedOrder->forceFill([
                        ...$paymentDetails,
                        'payment_status' => 'failed',
                    ])->save();
                } else {
                    $lockedOrder->forceFill($paymentDetails)->save();
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
            $transactionStatus = $validated['transaction_status'];
            $paymentDetails = array_filter([
                'midtrans_transaction_id' => $validated['transaction_id'] ?? null,
                'payment_type' => $validated['payment_type'] ?? null,
                'midtrans_transaction_status' => $transactionStatus,
            ], fn (?string $value): bool => $value !== null && $value !== '');

            if ($lockedParticipant->paid_at) {
                $lockedParticipant->forceFill($paymentDetails)->save();

                return;
            }

            $outcome = $this->paymentOutcome($validated);

            if ($outcome === 'paid') {
                $lockedParticipant->forceFill([
                    ...$paymentDetails,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ])->save();
                $lockedOrder->statusHistory()->create([
                    'user_id' => null,
                    'status' => $lockedOrder->status,
                    'note' => 'Pembayaran peserta berhasil diverifikasi oleh Midtrans.',
                ]);

                if (! $lockedOrder->participants()->where('payment_status', '!=', 'paid')->exists()) {
                    $lockedOrder->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ])->save();
                }
            } elseif ($outcome === 'failed') {
                $lockedParticipant->forceFill([
                    ...$paymentDetails,
                    'payment_status' => 'failed',
                ])->save();
            } else {
                $lockedParticipant->forceFill($paymentDetails)->save();
            }
        });

        return response()->json(['status' => 'ok']);
    }

    public function refreshAdvertisement(
        Request $request,
        AdCampaign $adCampaign,
        MidtransSnapService $midtrans,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user->role === 'admin'
                || ($user->role === 'vendor' && $adCampaign->vendor_user_id === $user->id),
            404,
        );

        try {
            $transaction = $midtrans->getTransactionStatus($adCampaign->order_id);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans advertisement status could not be refreshed.', [
                'campaign_id' => $adCampaign->id,
                'exception' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Status Midtrans belum berhasil diperiksa. Coba lagi sebentar.',
                ], 502);
            }

            return back()->withErrors([
                'payment' => 'Status Midtrans belum berhasil diperiksa. Coba lagi sebentar.',
            ]);
        }

        abort_unless(
            $transaction['order_id'] === $adCampaign->order_id
                && $this->amountMatches($transaction['gross_amount'], $adCampaign->amount),
            422,
        );

        DB::transaction(function () use ($adCampaign, $transaction): void {
            $campaign = AdCampaign::query()->lockForUpdate()->findOrFail($adCampaign->id);
            $transactionStatus = $transaction['transaction_status'];
            $paymentDetails = array_filter([
                'midtrans_transaction_id' => $transaction['transaction_id'] ?? null,
                'payment_type' => $transaction['payment_type'] ?? null,
                'midtrans_transaction_status' => $transactionStatus,
            ], fn (?string $value): bool => $value !== null && $value !== '');

            if (in_array($transactionStatus, ['refund', 'partial_refund'], true)) {
                $campaign->forceFill([
                    ...$paymentDetails,
                    'payment_status' => $transactionStatus === 'refund' ? 'refunded' : 'partially_refunded',
                    'refund_status' => $transactionStatus === 'refund' ? 'refunded' : 'partially_refunded',
                ])->save();

                return;
            }

            if ($transactionStatus === 'cancel') {
                $campaign->forceFill([
                    ...$paymentDetails,
                    'payment_status' => 'cancelled',
                    'refund_status' => $campaign->status === AdCampaign::STATUS_REJECTED
                        ? 'cancelled'
                        : $campaign->refund_status,
                    'status' => $campaign->paid_at ? $campaign->status : AdCampaign::STATUS_PAYMENT_FAILED,
                ])->save();

                return;
            }

            $paid = $transactionStatus === 'settlement'
                || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'accept');

            if ($paid) {
                $campaign->forceFill([
                    ...$paymentDetails,
                    'payment_status' => 'paid',
                    'status' => $campaign->status === AdCampaign::STATUS_REJECTED
                        ? AdCampaign::STATUS_REJECTED
                        : ($campaign->asset_path ? AdCampaign::STATUS_ASSET_SUBMITTED : AdCampaign::STATUS_PAID),
                    'paid_at' => $campaign->paid_at ?? now(),
                ])->save();

                return;
            }

            $failed = in_array($transactionStatus, ['deny', 'expire', 'failure'], true)
                || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'deny');

            if ($failed && ! $campaign->paid_at) {
                $campaign->forceFill([
                    ...$paymentDetails,
                    'payment_status' => $transactionStatus === 'capture' ? 'fraud_denied' : $transactionStatus,
                    'status' => AdCampaign::STATUS_PAYMENT_FAILED,
                ])->save();
            }
        });

        $adCampaign->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'payment_status' => $adCampaign->payment_status,
                'status' => $adCampaign->status,
            ]);
        }

        return back()->with(
            'status',
            $adCampaign->payment_status === 'paid'
                ? 'Pembayaran berhasil diverifikasi Midtrans.'
                : 'Status pembayaran Midtrans: '.$adCampaign->payment_status.'.',
        );
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
