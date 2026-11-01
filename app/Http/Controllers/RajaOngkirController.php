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

        try {
            return response()->json([
                'destinations' => $rajaOngkir->searchDestinations($validated['search']),
            ]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
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
        $validated = $request->validate([
            'courier' => ['required', 'string', Rule::in(config('services.rajaongkir.couriers'))],
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

        $weightDetails = $this->shippingWeightDetails($order, $quantity);

        try {
            return response()->json([
                'rates' => $rajaOngkir->shippingRates($origin, $destination, $weightDetails['packages'], $validated['courier']),
                'weight_grams' => $weightDetails['total_weight_grams'],
                'package_count' => $weightDetails['package_count'],
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
        $validated = $request->validate([
            'courier' => ['required', 'string', Rule::in(config('services.rajaongkir.couriers'))],
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
