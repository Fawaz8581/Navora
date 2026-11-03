<?php

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function createRajaOngkirVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
        'business_address' => 'Jalan Industri No. 10, Bandung',
        'rajaongkir_area_id' => '100',
        'rajaongkir_area_name' => 'Kecamatan Asal, Kota Asal',
    ]);

    return $vendor;
}

it('searches RajaOngkir locations using the server-side API key', function () {
    config([
        'services.rajaongkir.api_key' => 'rajaongkir-test-key',
        'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://rajaongkir.test/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                ['id' => 3204, 'label' => 'Coblong, Kota Bandung, Jawa Barat'],
            ],
        ]),
    ]);
    $buyer = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($buyer)
        ->getJson(route('rajaongkir.locations', ['search' => 'Coblong']))
        ->assertOk()
        ->assertJsonPath('destinations.0.id', '3204')
        ->assertJsonPath('destinations.0.label', 'Coblong, Kota Bandung, Jawa Barat');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('key', 'rajaongkir-test-key')
        && str_contains($request->url(), 'search=Coblong'));
});

it('searches Indonesian locations without a RajaOngkir key when simulated rates are enabled', function () {
    config([
        'services.rajaongkir.api_key' => null,
        'services.rajaongkir.simulated_rates' => true,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://photon.komoot.io/api/*' => Http::response([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'properties' => [
                        'osm_type' => 'R',
                        'osm_id' => 13290063,
                        'type' => 'district',
                        'name' => 'Coblong',
                        'city' => 'Bandung',
                        'state' => 'Jawa Barat',
                        'country' => 'Indonesia',
                        'countrycode' => 'ID',
                    ],
                ],
                [
                    'properties' => [
                        'osm_type' => 'R',
                        'osm_id' => 12345,
                        'type' => 'city',
                        'name' => 'Coblong',
                        'country' => 'France',
                        'countrycode' => 'FR',
                    ],
                ],
            ],
        ]),
    ]);
    $buyer = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($buyer)
        ->getJson(route('rajaongkir.locations', ['search' => 'Coblong']))
        ->assertOk()
        ->assertJsonPath('simulated', true)
        ->assertJsonPath('destinations.0.id', 'photon:R:13290063')
        ->assertJsonPath('destinations.0.label', 'Coblong, Bandung, Jawa Barat')
        ->assertJsonCount(1, 'destinations');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://photon.komoot.io/api/')
        && $request['q'] === 'Coblong'
        && $request->hasHeader('User-Agent'));
});

it('revalidates and stores shipping service, shipping fee, and the two-percent platform fee', function () {
    config([
        'services.rajaongkir.api_key' => 'rajaongkir-test-key',
        'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
        'services.rajaongkir.couriers' => ['jne'],
        'services.rajaongkir.simulated_rates' => false,
        'services.navora.platform_fee_percent' => 2,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://rajaongkir.test/api/v1/calculate/district/domestic-cost' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                [
                    'code' => 'jne',
                    'service' => 'REG',
                    'description' => 'Reguler',
                    'cost' => ['value' => 4000, 'etd' => '1-2'],
                ],
            ],
        ]),
    ]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createRajaOngkirVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 100,
        'packaging_details' => 'Kraft laminasi.',
        'delivery_contact_name' => 'Pembeli Navora',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'delivery_area_id' => '200',
        'delivery_area_name' => 'Kecamatan Tujuan, Kota Tujuan',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'subtotal_amount' => 100000,
        'platform_fee_amount' => 2000,
        'total_amount' => 102000,
        'shipping_weight_per_unit_grams' => 50,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.shipping-rates', $order).'?courier=jne')
        ->assertOk()
        ->assertJsonPath('weight_grams', 5000)
        ->assertJsonPath('rates.0.cost', 4000);

    $this->actingAs($buyer)
        ->postJson(route('orders.shipping-select', $order), [
            'courier' => 'jne',
            'service' => 'REG',
        ])
        ->assertOk()
        ->assertJsonPath('shipping_amount', 4000)
        ->assertJsonPath('platform_fee_percent', 2);

    expect($order->fresh()->subtotal_amount)->toBe(100000)
        ->and($order->fresh()->platform_fee_amount)->toBe(2000)
        ->and($order->fresh()->shipping_amount)->toBe(4000)
        ->and($order->fresh()->shipping_service)->toBe('REG')
        ->and($order->fresh()->total_amount)->toBe(106000);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request['origin'] === '100'
        && $request['destination'] === '200'
        && $request['weight'] === 5000
        && $request['courier'] === 'jne');
});

it('splits orders over 30 kilograms into courier-sized packages and sums each package rate', function () {
    config([
        'services.rajaongkir.api_key' => 'rajaongkir-test-key',
        'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
        'services.rajaongkir.couriers' => ['jne'],
        'services.rajaongkir.simulated_rates' => false,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://rajaongkir.test/api/v1/calculate/district/domestic-cost' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                [
                    'code' => 'jne',
                    'service' => 'REG',
                    'description' => 'Reguler',
                    'cost' => ['value' => 4000, 'etd' => '1-2'],
                ],
            ],
        ]),
    ]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createRajaOngkirVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 700,
        'packaging_details' => 'Kraft laminasi.',
        'delivery_contact_name' => 'Pembeli Navora',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'delivery_area_id' => '200',
        'delivery_area_name' => 'Kecamatan Tujuan, Kota Tujuan',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'shipping_weight_per_unit_grams' => 50,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.shipping-rates', $order).'?courier=jne')
        ->assertOk()
        ->assertJsonPath('weight_grams', 35000)
        ->assertJsonPath('package_count', 2)
        ->assertJsonPath('rates.0.cost', 8000);

    $this->actingAs($buyer)
        ->postJson(route('orders.shipping-select', $order), [
            'courier' => 'jne',
            'service' => 'REG',
        ])
        ->assertOk()
        ->assertJsonPath('shipping_amount', 8000);

    expect($order->fresh()->shipping_amount)->toBe(8000);

    Http::assertSent(fn (Request $request): bool => $request['weight'] === 30000);
    Http::assertSent(fn (Request $request): bool => $request['weight'] === 5000);
});

it('does not accept a shipping service that is absent from the fresh RajaOngkir response', function () {
    config([
        'services.rajaongkir.api_key' => 'rajaongkir-test-key',
        'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
        'services.rajaongkir.couriers' => ['jne'],
        'services.rajaongkir.simulated_rates' => false,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://rajaongkir.test/api/v1/calculate/district/domestic-cost' => Http::response([
            'meta' => ['status' => 'success'],
            'data' => [
                ['code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => ['value' => 4000]],
            ],
        ]),
    ]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createRajaOngkirVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 1,
        'packaging_details' => 'Kraft laminasi.',
        'delivery_contact_name' => 'Pembeli Navora',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'delivery_area_id' => '200',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'shipping_weight_per_unit_grams' => 50,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->postJson(route('orders.shipping-select', $order), [
            'courier' => 'jne',
            'service' => 'EXP',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service']);

    expect($order->fresh()->shipping_service)->toBeNull();
});

it('allows flexible product weight and uses size-independent simulated courier rates', function () {
    config([
        'services.rajaongkir.couriers' => ['jne'],
        'services.rajaongkir.simulated_rates' => true,
    ]);
    Http::preventStrayRequests();
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createRajaOngkirVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 1,
        'packaging_details' => 'Kraft laminasi.',
        'status' => Order::STATUS_WAITING,
        'payment_status' => 'awaiting_quote',
        'quote_status' => 'awaiting_vendor',
    ]);

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $order), [
            'unit_price' => 1000,
            'shipping_weight_per_unit_grams' => 45000,
        ])
        ->assertOk();

    expect($order->fresh()->shipping_weight_per_unit_grams)->toBe(45000);

    $this->actingAs($buyer)
        ->postJson(route('orders.quote-decision', $order), ['decision' => 'accept'])
        ->assertOk();

    $this->actingAs($buyer)
        ->putJson(route('orders.delivery-details', $order), [
            'delivery_contact_name' => 'Pembeli Navora',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Kecamatan Tujuan, Kota Tujuan',
        ])
        ->assertOk();

    $this->actingAs($buyer)
        ->getJson(route('orders.shipping-rates', $order).'?courier=jne')
        ->assertOk()
        ->assertJsonPath('simulated', true)
        ->assertJsonPath('rates.0.cost', 15000)
        ->assertJsonPath('rates.1.cost', 25000);

    $this->actingAs($buyer)
        ->postJson(route('orders.shipping-select', $order), [
            'courier' => 'jne',
            'service' => 'REG',
        ])
        ->assertOk()
        ->assertJsonPath('shipping_amount', 15000);

    expect($order->fresh()->shipping_amount)->toBe(15000)
        ->and($order->fresh()->shipping_weight_per_unit_grams)->toBe(45000);
});

it('explains separately when vendor-origin and buyer-destination locations are missing', function () {
    config(['services.rajaongkir.couriers' => ['jne']]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
        'business_address' => 'Jalan Industri No. 10, Kota Asal',
    ]);
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 1,
        'packaging_details' => 'Kraft laminasi.',
        'delivery_contact_name' => 'Pembeli Navora',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'delivery_area_id' => '200',
        'delivery_area_name' => 'Coblong, Kota Bandung',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'shipping_weight_per_unit_grams' => 50,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.shipping-rates', $order).'?courier=jne')
        ->assertUnprocessable()
        ->assertJsonPath('errors.shipping.0', 'Vendor belum memilih area asal pengiriman di Profil usaha. Minta vendor memperbarui lokasinya.');

    $vendor->businessProfile->forceFill([
        'business_address' => 'Jalan Industri No. 10, Bandung',
        'rajaongkir_area_id' => '100',
        'rajaongkir_area_name' => 'Coblong, Kota Bandung',
    ])->save();
    $order->forceFill([
        'delivery_area_id' => null,
        'delivery_area_name' => null,
    ])->save();

    $this->actingAs($buyer)
        ->getJson(route('orders.shipping-rates', $order).'?courier=jne')
        ->assertUnprocessable()
        ->assertJsonPath('errors.shipping.0', 'Lengkapi area tujuan penerima dari tab Pesanan sebelum menghitung ongkir.');
});
