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
     * @return list<array{id: string, label: string}>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function searchSimulatedDestinations(string $search): array
    {
        $response = Http::acceptJson()
            ->withUserAgent('Navora location search ('.config('app.url').')')
            ->timeout(10)
            ->get('https://photon.komoot.io/api/', [
                'q' => $search,
                'countrycode' => 'ID',
                'limit' => 30,
            ])
            ->throw();
        $body = $response->json();
        $features = is_array($body) ? ($body['features'] ?? null) : null;

        if (! is_array($features)) {
            throw new RuntimeException('Respons pencarian lokasi simulasi tidak valid.');
        }

        $destinations = [];
        foreach ($features as $feature) {
            $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
            $osmType = is_array($properties) ? ($properties['osm_type'] ?? null) : null;
            $osmId = is_array($properties) ? ($properties['osm_id'] ?? null) : null;
            $countryCode = is_array($properties) ? ($properties['countrycode'] ?? null) : null;
            $country = is_array($properties) ? ($properties['country'] ?? null) : null;
            $name = is_array($properties) ? ($properties['name'] ?? null) : null;

            if ((! is_string($countryCode) || strtoupper($countryCode) !== 'ID')
                && (! is_string($country) || mb_strtolower($country) !== 'indonesia')) {
                continue;
            }

            if (! is_string($osmType)
                || ! is_numeric($osmId)
                || ! is_string($name)
                || $name === '') {
                continue;
            }

            $label = implode(', ', array_unique(array_filter([
                $name,
                $properties['district'] ?? null,
                $properties['city'] ?? null,
                $properties['county'] ?? null,
                $properties['state'] ?? null,
            ], fn (mixed $part): bool => is_string($part) && $part !== '')));

            $destinations[] = [
                'id' => 'photon:'.$osmType.':'.$osmId,
                'label' => $label,
            ];
        }

        return array_slice($destinations, 0, 15);
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
