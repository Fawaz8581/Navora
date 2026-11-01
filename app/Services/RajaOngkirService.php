<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RajaOngkirService
{
    /**
     * @return list<array{id: string, label: string}>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function searchDestinations(string $search): array
    {
        $data = $this->request('get', 'destination/domestic-destination', [
            'search' => $search,
            'limit' => 15,
        ]);
        $destinations = [];

        foreach ($data as $destination) {
            if (! is_array($destination) || ! isset($destination['id'])) {
                continue;
            }

            $label = $destination['label'] ?? implode(', ', array_filter([
                $destination['subdistrict_name'] ?? null,
                $destination['district_name'] ?? null,
                $destination['city_name'] ?? null,
                $destination['province_name'] ?? null,
            ], 'is_string'));

            if (is_string($label) && $label !== '') {
                $destinations[] = [
                    'id' => (string) $destination['id'],
                    'label' => $label,
                ];
            }
        }

        return $destinations;
    }

    /**
     * @param  array<int, int>  $packageWeightsAndCounts
     * @return list<array{code: string, service: string, description: string, cost: int, etd: string}>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function shippingRates(
        string $origin,
        string $destination,
        array $packageWeightsAndCounts,
        string $courier,
    ): array {
        $aggregatedRates = null;

        foreach ($packageWeightsAndCounts as $weightInGrams => $packageCount) {
            if ($weightInGrams < 1 || $weightInGrams > 30000 || $packageCount < 1) {
                throw new RuntimeException('Berat atau jumlah paket pengiriman tidak valid.');
            }

            $data = $this->request('post', 'calculate/district/domestic-cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weightInGrams,
                'courier' => $courier,
            ]);
            $packageRates = [];

            foreach ($data as $rate) {
                if (! is_array($rate) || ! is_array($rate['cost'] ?? null)) {
                    continue;
                }

                $cost = $rate['cost']['value'] ?? null;
                $service = $rate['service'] ?? null;
                $code = $rate['code'] ?? null;

                if (! is_numeric($cost)
                    || (int) $cost < 0
                    || ! is_string($service)
                    || $service === ''
                    || ! is_string($code)
                    || $code === '') {
                    continue;
                }

                $cost = (int) $cost;
                if ($cost > intdiv(PHP_INT_MAX, $packageCount)) {
                    throw new RuntimeException('Total tarif pengiriman melebihi batas yang dapat dihitung.');
                }

                $key = $code."\0".$service;
                $packageRates[$key] = [
                    'code' => $code,
                    'service' => $service,
                    'description' => is_string($rate['description'] ?? null) ? $rate['description'] : $service,
                    'cost' => $cost * $packageCount,
                    'etd' => is_string($rate['cost']['etd'] ?? null) ? $rate['cost']['etd'] : '',
                ];
            }

            if ($aggregatedRates === null) {
                $aggregatedRates = $packageRates;

                continue;
            }

            foreach ($aggregatedRates as $key => &$aggregatedRate) {
                if (! isset($packageRates[$key])) {
                    unset($aggregatedRates[$key]);

                    continue;
                }

                if ($packageRates[$key]['cost'] > PHP_INT_MAX - $aggregatedRate['cost']) {
                    throw new RuntimeException('Total tarif pengiriman melebihi batas yang dapat dihitung.');
                }

                $aggregatedRate['cost'] += $packageRates[$key]['cost'];
            }
            unset($aggregatedRate);
        }

        return array_values($aggregatedRates ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    private function request(string $method, string $endpoint, array $parameters): array
    {
        $apiKey = config('services.rajaongkir.api_key');
        $baseUrl = rtrim((string) config('services.rajaongkir.base_url'), '/');

        if (! is_string($apiKey) || $apiKey === '' || $baseUrl === '') {
            throw new RuntimeException('API RajaOngkir belum dikonfigurasi.');
        }

        $request = Http::acceptJson()
            ->withHeaders(['key' => $apiKey])
            ->timeout(15);
        $response = $method === 'get'
            ? $request->get($baseUrl.'/'.$endpoint, $parameters)
            : $request->asForm()->post($baseUrl.'/'.$endpoint, $parameters);
        $response->throw();

        $body = $response->json();
        $data = is_array($body) ? ($body['data'] ?? null) : null;

        if (! is_array($data)
            || (isset($body['meta']['status']) && $body['meta']['status'] !== 'success')) {
            throw new RuntimeException('Respons API RajaOngkir tidak valid.');
        }

        return array_is_list($data) ? $data : [$data];
    }
}
