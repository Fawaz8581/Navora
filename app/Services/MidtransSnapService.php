<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransSnapService
{
    /**
     * @return array{token: string, redirect_url: string}
     */
    public function createTransaction(AdCampaign $campaign, User $vendor): array
    {
        return $this->requestTransaction(
            $campaign->order_id,
            $campaign->amount,
            $vendor,
            route('vendor.advertisements.index'),
        );
    }

    /**
     * @return array{token: string, redirect_url: string}
     */
    public function createOrderTransaction(string $orderId, int $amount, User $buyer): array
    {
        return $this->requestTransaction(
            $orderId,
            $amount,
            $buyer,
            route('workspace'),
        );
    }

    /**
     * @return array{
     *     status_code: string,
     *     order_id: string,
     *     gross_amount: string,
     *     transaction_status: string,
     *     transaction_id?: string,
     *     payment_type?: string,
     *     fraud_status?: string
     * }
     */
    public function getTransactionStatus(string $orderId): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new RuntimeException('Kunci server Midtrans belum dikonfigurasi.');
        }

        $baseUrl = rtrim((string) config('services.midtrans.api_base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('URL API Midtrans belum dikonfigurasi.');
        }

        $response = Http::acceptJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(15)
            ->get($baseUrl.'/v2/'.rawurlencode($orderId).'/status');

        $response->throw();

        $data = $response->json();

        if (! is_array($data)
            || (string) ($data['status_code'] ?? '') !== '200'
            || ($data['order_id'] ?? null) !== $orderId
            || ! is_string($data['gross_amount'] ?? null)
            || ! is_string($data['transaction_status'] ?? null)) {
            throw new RuntimeException('Midtrans tidak mengembalikan status transaksi yang valid.');
        }

        return $data;
    }

    /**
     * @return array{transaction_id: string, order_id: string, transaction_status: string, refund_key: ?string}
     */
    public function refundAdvertisement(AdCampaign $campaign, string $reason): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new RuntimeException('Kunci server Midtrans belum dikonfigurasi.');
        }

        $baseUrl = rtrim((string) config('services.midtrans.api_base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('URL Midtrans belum dikonfigurasi.');
        }

        if (! is_string($campaign->refund_key) || $campaign->refund_key === '') {
            throw new RuntimeException('Kunci refund iklan belum tersedia.');
        }

        $transactionReference = $campaign->midtrans_transaction_id ?: $campaign->order_id;
        $isCapture = $campaign->midtrans_transaction_status === 'capture';
        $action = $isCapture ? 'cancel' : 'refund';
        $refundRequest = Http::acceptJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(15);
        $refundUrl = $baseUrl.'/v2/'.rawurlencode($transactionReference).'/'.$action;

        $response = $isCapture
            ? $refundRequest->post($refundUrl)
            : $refundRequest->asJson()->post($refundUrl, [
                'refund_key' => $campaign->refund_key,
                'amount' => $campaign->amount,
                'reason' => mb_substr('Iklan ditolak: '.$reason, 0, 255),
            ]);

        $response->throw();

        $data = $response->json();
        $transactionId = is_array($data) ? ($data['transaction_id'] ?? null) : null;
        $orderId = is_array($data) ? ($data['order_id'] ?? null) : null;
        $transactionStatus = is_array($data) ? ($data['transaction_status'] ?? null) : null;
        $refundKey = is_array($data) ? ($data['refund_key'] ?? null) : null;

        if (! is_array($data)
            || (string) ($data['status_code'] ?? '') !== '200'
            || $orderId !== $campaign->order_id
            || ! is_string($transactionId)
            || ($isCapture
                ? $transactionStatus !== 'cancel'
                : (! in_array($transactionStatus, ['refund', 'partial_refund'], true)
                    || $refundKey !== $campaign->refund_key))) {
            throw new RuntimeException('Midtrans belum mengonfirmasi permintaan refund iklan.');
        }

        return [
            'transaction_id' => $transactionId,
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'refund_key' => $refundKey,
        ];
    }

    /**
     * @return array{token: string, redirect_url: string}
     */
    private function requestTransaction(string $orderId, int $amount, User $customer, string $finishUrl): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new RuntimeException('Kunci server Midtrans belum dikonfigurasi.');
        }

        $baseUrl = rtrim((string) config('services.midtrans.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('URL Midtrans belum dikonfigurasi.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth($serverKey, '')
            ->timeout(15)
            ->post($baseUrl.'/snap/v1/transactions', [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount,
                ],
                'customer_details' => [
                    'first_name' => $customer->name,
                    'email' => $customer->email,
                ],
                'callbacks' => [
                    'finish' => $finishUrl,
                ],
            ]);

        $response->throw();

        $data = $response->json();
        $token = is_array($data) ? ($data['token'] ?? null) : null;
        $redirectUrl = is_array($data) ? ($data['redirect_url'] ?? null) : null;

        if (! is_string($token) || ! is_string($redirectUrl)) {
            throw new RuntimeException('Midtrans tidak mengembalikan informasi checkout yang valid.');
        }

        $redirectHost = parse_url($redirectUrl, PHP_URL_HOST);
        $redirectScheme = parse_url($redirectUrl, PHP_URL_SCHEME);

        if ($redirectScheme !== 'https'
            || ! in_array($redirectHost, ['app.midtrans.com', 'app.sandbox.midtrans.com'], true)) {
            throw new RuntimeException('Midtrans mengembalikan alamat checkout yang tidak valid.');
        }

        return [
            'token' => $token,
            'redirect_url' => $redirectUrl,
        ];
    }
}
