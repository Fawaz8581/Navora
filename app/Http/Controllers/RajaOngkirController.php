<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderParticipant;
use App\Models\User;
use App\Services\RajaOngkirService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RajaOngkirController extends Controller
{
    public function locations(Request $request, RajaOngkirService $rajaOngkir): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:3', 'max:100'],
        ]);

        if (config('services.rajaongkir.simulated_rates') && ! filled(config('services.rajaongkir.api_key'))) {
            try {
                return response()->json([
                    'destinations' => $rajaOngkir->searchSimulatedDestinations($validated['search']),
                    'simulated' => true,
                ]);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::warning('Simulated location lookup failed.', [
                    'exception' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Pencarian lokasi sedang tidak tersedia. Coba lagi sebentar.',
                ], 502);
            }
        }

        try {
            return response()->json([
                'destinations' => $rajaOngkir->searchDestinations($validated['search']),
                'simulated' => false,
            ]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            if (config('services.rajaongkir.simulated_rates')) {
                try {
                    Log::notice('RajaOngkir location search failed; using simulated location search.', [
                        'exception' => $exception->getMessage(),
                    ]);

                    return response()->json([
                        'destinations' => $rajaOngkir->searchSimulatedDestinations($validated['search']),
                        'simulated' => true,
                    ]);
                } catch (ConnectionException|RequestException|RuntimeException $fallbackException) {
                    Log::warning('Simulated location lookup failed.', [
                        'exception' => $fallbackException->getMessage(),
                    ]);
                }
            }

            Log::warning('RajaOngkir destination lookup failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Lokasi belum berhasil dicari. Coba lagi sebentar.',
            ], 502);
        }
    }

    public function rates(Request $request, Order $order, RajaOngkirService $rajaOngkir): JsonResponse
    {
        $user = $request->user();
        $participant = $this->buyerContext($order, $user);
        abort_unless($order->quote_status === 'accepted', 422, 'Setujui penawaran harga sebelum menghitung ongkir.');
        $allowedCouriers = array_merge(config('services.rajaongkir.couriers'), ['vendor']);
        $validated = $request->validate([
            'courier' => ['required', 'string', Rule::in($allowedCouriers)],
        ]);
        $vendorProfile = $order->vendor?->businessProfile;
        $origin = $vendorProfile?->rajaongkir_area_id;
        $destination = $participant?->delivery_area_id ?? $order->delivery_area_id;
        $quantity = $participant?->quantity ?? $order->quantity;

        if (! filled($vendorProfile?->business_address)) {
            throw ValidationException::withMessages([
                'shipping' => 'Vendor belum mengisi alamat lengkap di Profil usaha. Minta vendor melengkapinya sebelum menghitung ongkir.',
            ]);
        }

        if (! is_string($origin) || $origin === '') {
            throw ValidationException::withMessages([
                'shipping' => 'Vendor belum memilih area asal pengiriman di Profil usaha. Minta vendor memperbarui lokasinya.',
            ]);
        }

        if (! is_string($destination) || $destination === '') {
            throw ValidationException::withMessages([
                'shipping' => 'Lengkapi area tujuan penerima dari tab Pesanan sebelum menghitung ongkir.',
            ]);
        }

        if ($participant
            ? (! filled($participant->delivery_contact_name)
                || ! filled($participant->delivery_contact_phone)
                || ! filled($participant->delivery_address))
            : (! filled($order->delivery_contact_name)
                || ! filled($order->delivery_contact_phone)
                || ! filled($order->delivery_address))) {
            throw ValidationException::withMessages([
                'shipping' => 'Lengkapi nama, nomor telepon, dan alamat penerima di tab Pesanan sebelum menghitung ongkir.',
            ]);
        }

        if ($validated['courier'] === 'vendor') {
            return response()->json([
                'rates' => [
                    [
                        'code' => 'vendor',
                        'service' => 'Vendor Delivery',
                        'description' => 'Pengiriman oleh vendor (gratis / ditanggung vendor)',
                        'cost' => 0,
                        'etd' => 'Sesuai kesepakatan',
                    ],
                ],
                'simulated' => true,
            ]);
        }

        if (config('services.rajaongkir.simulated_rates')) {
            return response()->json([
                'rates' => $this->simulatedRates($validated['courier']),
                'simulated' => true,
            ]);
        }

        $weightDetails = $this->shippingWeightDetails($order, $quantity);

        try {
            return response()->json([
                'rates' => $rajaOngkir->shippingRates($origin, $destination, $weightDetails['packages'], $validated['courier']),
                'weight_grams' => $weightDetails['total_weight_grams'],
                'package_count' => $weightDetails['package_count'],
                'simulated' => false,
            ]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('RajaOngkir shipping calculation failed.', [
                'order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Tarif pengiriman belum berhasil dihitung. Coba lagi sebentar.',
            ], 502);
        }
    }

    public function selectRate(Request $request, Order $order, RajaOngkirService $rajaOngkir): JsonResponse
    {
        $user = $request->user();
        $participant = $this->buyerContext($order, $user);
        abort_unless($order->quote_status === 'accepted', 422, 'Setujui penawaran harga sebelum memilih ongkir.');
        $allowedCouriers = array_merge(config('services.rajaongkir.couriers'), ['vendor']);
        $validated = $request->validate([
            'courier' => ['required', 'string', Rule::in($allowedCouriers)],
            'service' => ['required', 'string', 'max:100'],
        ]);
        $vendorProfile = $order->vendor?->businessProfile;
        $origin = $vendorProfile?->rajaongkir_area_id;
        $destination = $participant?->delivery_area_id ?? $order->delivery_area_id;
        $quantity = $participant?->quantity ?? $order->quantity;

        if (! filled($vendorProfile?->business_address)) {
            throw ValidationException::withMessages([
                'shipping' => 'Vendor belum mengisi alamat lengkap di Profil usaha. Minta vendor melengkapinya sebelum memilih ongkir.',
            ]);
        }

        if (! is_string($origin) || $origin === '') {
            throw ValidationException::withMessages([
                'shipping' => 'Vendor belum memilih area asal pengiriman di Profil usaha. Minta vendor memperbarui lokasinya.',
            ]);
        }

        if (! is_string($destination) || $destination === '') {
            throw ValidationException::withMessages([
                'shipping' => 'Lengkapi area tujuan penerima dari tab Pesanan sebelum memilih ongkir.',
            ]);
        }

        if ($participant
            ? (! filled($participant->delivery_contact_name)
                || ! filled($participant->delivery_contact_phone)
                || ! filled($participant->delivery_address))
            : (! filled($order->delivery_contact_name)
                || ! filled($order->delivery_contact_phone)
                || ! filled($order->delivery_address))) {
            throw ValidationException::withMessages([
                'shipping' => 'Lengkapi nama, nomor telepon, dan alamat penerima di tab Pesanan sebelum memilih ongkir.',
            ]);
        }

        if ($validated['courier'] === 'vendor') {
            $rates = [
                [
                    'code' => 'vendor',
                    'service' => 'Vendor Delivery',
                    'description' => 'Pengiriman oleh vendor (gratis / ditanggung vendor)',
                    'cost' => 0,
                    'etd' => 'Sesuai kesepakatan',
                ],
            ];
        } elseif (config('services.rajaongkir.simulated_rates')) {
            $rates = $this->simulatedRates($validated['courier']);
        } else {
            $weightDetails = $this->shippingWeightDetails($order, $quantity);

            try {
                $rates = $rajaOngkir->shippingRates($origin, $destination, $weightDetails['packages'], $validated['courier']);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::warning('RajaOngkir shipping service validation failed.', [
                    'order_id' => $order->id,
                    'exception' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Tarif pengiriman belum berhasil diverifikasi. Coba lagi sebentar.',
                ], 502);
            }
        }

        $selectedRate = collect($rates)->first(fn (array $rate): bool => $rate['service'] === $validated['service']);
        if (! $selectedRate) {
            throw ValidationException::withMessages([
                'service' => 'Layanan pengiriman tersebut sudah tidak tersedia. Perbarui pilihan tarif.',
            ]);
        }

        DB::transaction(function () use ($order, $participant, $selectedRate, $validated): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $subtotal = (int) $lockedOrder->unit_price * ($participant?->quantity ?? $lockedOrder->quantity);
            $fee = Order::platformFeeFor($subtotal);
            $shippingAmount = (int) $selectedRate['cost'];

            if ($participant) {
                $lockedParticipant = $lockedOrder->participants()->lockForUpdate()->findOrFail($participant->id);
                $lockedParticipant->forceFill([
                    'subtotal_amount' => $subtotal,
                    'shipping_amount' => $shippingAmount,
                    'platform_fee_amount' => $fee,
                    'shipping_courier' => $validated['courier'],
                    'shipping_service' => $selectedRate['service'],
                    'payment_amount' => $subtotal + $shippingAmount + $fee,
                ])->save();

                $lockedOrder->forceFill([
                    'subtotal_amount' => (int) $lockedOrder->participants()->sum('subtotal_amount'),
                    'shipping_amount' => (int) $lockedOrder->participants()->sum('shipping_amount'),
                    'platform_fee_amount' => (int) $lockedOrder->participants()->sum('platform_fee_amount'),
                    'total_amount' => (int) $lockedOrder->participants()->sum('payment_amount'),
                ])->save();
            } else {
                $lockedOrder->forceFill([
                    'subtotal_amount' => $subtotal,
                    'shipping_amount' => $shippingAmount,
                    'platform_fee_amount' => $fee,
                    'shipping_courier' => $validated['courier'],
                    'shipping_service' => $selectedRate['service'],
                    'total_amount' => $subtotal + $shippingAmount + $fee,
                ])->save();
            }
        });

        return response()->json([
            'shipping_amount' => (int) $selectedRate['cost'],
            'service' => $selectedRate['service'],
            'platform_fee_percent' => config('services.navora.platform_fee_percent'),
        ]);
    }

    private function buyerContext(Order $order, User $user): ?OrderParticipant
    {
        abort_unless($user->role === 'umkm', 403);

        if ($order->type === Order::TYPE_DIRECT) {
            abort_unless($order->user_id === $user->id, 404);

            return null;
        }

        return $order->participants()->where('user_id', $user->id)->firstOrFail();
    }

    /**
     * @return list<array{code: string, service: string, description: string, cost: int, etd: string}>
     */
    private function simulatedRates(string $courier): array
    {
        $rates = match ($courier) {
            'jne' => [['REG', 'Reguler', 15000, '2-4 hari'], ['YES', 'Yes', 25000, '1-2 hari']],
            'pos' => [['PKT', 'Paket Kilat', 14000, '2-5 hari'], ['EXP', 'Express', 24000, '1-2 hari']],
            'tiki' => [['REG', 'Reguler', 16000, '2-4 hari'], ['ONS', 'Over Night Service', 27000, '1 hari']],
            'jnt' => [['EZ', 'Reguler', 14000, '2-4 hari'], ['SUPER', 'J&T Super', 24000, '1-2 hari']],
            'sicepat' => [['REG', 'Reguler', 15000, '2-4 hari'], ['BEST', 'Besok Sampai', 25000, '1 hari']],
            default => [['REG', 'Reguler', 15000, '2-5 hari'], ['EXP', 'Express', 25000, '1-2 hari']],
        };

        return array_map(fn (array $rate): array => [
            'code' => $courier,
            'service' => $rate[0],
            'description' => $rate[1].' (tarif simulasi)',
            'cost' => $rate[2],
            'etd' => $rate[3],
        ], $rates);
    }

    /**
     * @return array{total_weight_grams: int, package_count: int, packages: array<int, int>}
     */
    private function shippingWeightDetails(Order $order, int $quantity): array
    {
        $unitWeight = (int) $order->shipping_weight_per_unit_grams;

        if ($quantity < 1 || ! $order->unit_price || $unitWeight < 1 || $unitWeight > 30000) {
            throw ValidationException::withMessages([
                'shipping' => 'Vendor harus menetapkan harga dan berat produk per pcs antara 1 gram dan 30 kg, dengan jumlah pesanan minimal 1 pcs.',
            ]);
        }

        $unitsPerPackage = intdiv(30000, $unitWeight);
        $fullPackageCount = intdiv($quantity, $unitsPerPackage);
        $remainingUnits = $quantity % $unitsPerPackage;
        $packages = [];

        if ($fullPackageCount > 0) {
            $packages[$unitsPerPackage * $unitWeight] = $fullPackageCount;
        }

        if ($remainingUnits > 0) {
            $remainingPackageWeight = $remainingUnits * $unitWeight;
            $packages[$remainingPackageWeight] = ($packages[$remainingPackageWeight] ?? 0) + 1;
        }

        return [
            'total_weight_grams' => $quantity * $unitWeight,
            'package_count' => $fullPackageCount + ($remainingUnits > 0 ? 1 : 0),
            'packages' => $packages,
        ];
    }
}
