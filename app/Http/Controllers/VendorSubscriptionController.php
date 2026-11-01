<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VendorSubscription;
use App\Services\MidtransSnapService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VendorSubscriptionController extends Controller
{
    public function checkout(Request $request, MidtransSnapService $midtrans): JsonResponse
    {
        $vendor = $request->user();
        abort_unless($vendor->role === 'vendor', 403);

        $payment = DB::transaction(function () use ($vendor): array {
            User::query()->lockForUpdate()->findOrFail($vendor->id);

            if (VendorSubscription::query()
                ->where('vendor_user_id', $vendor->id)
                ->where('payment_status', 'paid')
                ->where('ends_at', '>', now())
                ->exists()) {
                abort(Response::HTTP_CONFLICT, 'Paket premium vendor masih aktif.');
            }

            $pending = VendorSubscription::query()
                ->where('vendor_user_id', $vendor->id)
                ->where('payment_status', 'pending')
                ->latest('id')
                ->first();

            if ($pending) {
                return ['token' => $pending->snap_token, 'subscription_id' => $pending->id];
            }

            $subscription = VendorSubscription::query()->create([
                'vendor_user_id' => $vendor->id,
                'order_id' => 'NAVORA-SUB-'.Str::uuid(),
                'amount' => config('services.navora.vendor_subscription_amount'),
                'payment_status' => 'pending',
            ]);

            return ['subscription_id' => $subscription->id, 'token' => null];
        });

        if ($payment['token'] !== null) {
            return response()->json([
                'token' => $payment['token'],
                'subscription_id' => $payment['subscription_id'],
            ]);
        }

        $subscription = VendorSubscription::query()->findOrFail($payment['subscription_id']);

        try {
            $checkout = $midtrans->createVendorSubscriptionTransaction($subscription, $vendor);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans vendor subscription checkout could not be created.', [
                'vendor_user_id' => $vendor->id,
                'subscription_id' => $subscription->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Checkout Midtrans belum berhasil dibuat. Silakan coba lagi.',
            ], 502);
        }

        $subscription->forceFill([
            'snap_token' => $checkout['token'],
            'snap_redirect_url' => $checkout['redirect_url'],
        ])->save();

        return response()->json([
            'token' => $checkout['token'],
            'subscription_id' => $subscription->id,
        ]);
    }

    public function refresh(
        Request $request,
        VendorSubscription $vendorSubscription,
        MidtransSnapService $midtrans,
    ): JsonResponse {
        $vendor = $request->user();
        abort_unless($vendor->role === 'vendor' && $vendorSubscription->vendor_user_id === $vendor->id, 404);

        try {
            $transaction = $midtrans->getTransactionStatus($vendorSubscription->order_id);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Midtrans vendor subscription status could not be refreshed.', [
                'subscription_id' => $vendorSubscription->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Status Midtrans belum berhasil diperiksa. Coba lagi sebentar.',
            ], 502);
        }

        if ($transaction['order_id'] !== $vendorSubscription->order_id
            || ! $this->amountMatches($transaction['gross_amount'], $vendorSubscription->amount)) {
            abort(422, 'Data transaksi Midtrans tidak cocok dengan paket vendor.');
        }

        $transactionStatus = $transaction['transaction_status'];
        $paid = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'accept');
        $failed = in_array($transactionStatus, ['deny', 'cancel', 'expire', 'failure'], true)
            || ($transactionStatus === 'capture' && ($transaction['fraud_status'] ?? null) === 'deny');

        DB::transaction(function () use ($vendorSubscription, $transaction, $transactionStatus, $paid, $failed): void {
            $subscription = VendorSubscription::query()->lockForUpdate()->findOrFail($vendorSubscription->id);
            $details = array_filter([
                'midtrans_transaction_id' => $transaction['transaction_id'] ?? null,
                'payment_type' => $transaction['payment_type'] ?? null,
                'midtrans_transaction_status' => $transactionStatus,
            ], fn (?string $value): bool => $value !== null && $value !== '');

            if ($paid && ! $subscription->paid_at) {
                $subscription->forceFill([
                    ...$details,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(config('services.navora.vendor_subscription_days')),
                ])->save();
            } elseif ($failed && ! $subscription->paid_at) {
                $subscription->forceFill([...$details, 'payment_status' => 'failed'])->save();
            } else {
                $subscription->forceFill($details)->save();
            }
        });

        $vendorSubscription->refresh();

        return response()->json([
            'payment_status' => $vendorSubscription->payment_status,
            'is_active' => $vendorSubscription->isActive(),
            'ends_at' => $vendorSubscription->ends_at?->toIso8601String(),
        ]);
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
