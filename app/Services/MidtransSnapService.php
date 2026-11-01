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
                    'order_id' => $campaign->order_id,
                    'gross_amount' => $campaign->amount,
                ],
                'customer_details' => [
                    'first_name' => $vendor->name,
                    'email' => $vendor->email,
                ],
                'callbacks' => [
                    'finish' => route('vendor.advertisements.index'),
                ],
            ]);

        $response->throw();

        $data = $response->json();
        $token = is_array($data) ? ($data['token'] ?? null) : null;
        $redirectUrl = is_array($data) ? ($data['redirect_url'] ?? null) : null;

        if (! is_string($token) || ! is_string($redirectUrl)) {
            throw new RuntimeException('Midtrans tidak mengembalikan informasi checkout yang valid.');
        }

        return [
            'token' => $token,
            'redirect_url' => $redirectUrl,
        ];
    }
}
