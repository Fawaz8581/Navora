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
