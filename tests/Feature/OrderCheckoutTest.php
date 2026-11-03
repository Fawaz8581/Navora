<?php

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\OrderParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCheckoutPageVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
        'business_name' => 'Vendor Pengirim',
        'business_address' => 'Jalan Industri 10, Bandung',
        'rajaongkir_area_id' => '100',
        'rajaongkir_area_name' => 'Kecamatan Asal, Kota Asal',
    ]);

    return $vendor;
}

it('renders the checkout page with the vendor address, recipient details, and payment summary', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createCheckoutPageVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 50,
        'packaging_details' => 'Kraft laminasi.',
        'delivery_contact_name' => 'Penerima Pesanan',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'delivery_area_id' => '200',
        'delivery_area_name' => 'Kecamatan Tujuan, Kota Tujuan',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'subtotal_amount' => 50000,
        'platform_fee_amount' => 1000,
        'total_amount' => 51000,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('order.can_checkout', true)
        ->assertJsonPath('order.can_pay', false);

    $this->actingAs($buyer)
        ->get(route('orders.checkout-page', $order))
        ->assertOk()
        ->assertViewIs('orders.checkout')
        ->assertSee('Alamat pengirim')
        ->assertSee('Jalan Industri 10, Bandung')
        ->assertSee('Penerima Pesanan')
        ->assertSee('Jalan Melati 10, Bandung')
        ->assertSee('Biodata penerima')
        ->assertSee('Pilih jasa ekspedisi')
        ->assertSee('MIDTRANS_SERVER_KEY')
        ->assertSee('Rp51.000');
});

it('shows each group-order buyer their own checkout delivery details and payment share', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $creator = User::factory()->create(['role' => 'umkm']);
    $vendor = createCheckoutPageVendor();
    $order = Order::query()->create([
        'user_id' => $creator->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_GROUP,
        'product_name' => 'Kotak karton',
        'quantity' => 100,
        'packaging_details' => 'Karton kraft.',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'subtotal_amount' => 20000,
        'platform_fee_amount' => 400,
        'total_amount' => 20400,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);
    OrderParticipant::query()->create([
        'order_id' => $order->id,
        'user_id' => $buyer->id,
        'quantity' => 20,
        'delivery_contact_name' => 'Peserta Checkout',
        'delivery_contact_phone' => '+6281234567890',
        'delivery_address' => 'Jalan Peserta 20',
        'delivery_area_id' => '201',
        'delivery_area_name' => 'Kota Peserta',
        'subtotal_amount' => 20000,
        'platform_fee_amount' => 400,
        'payment_amount' => 20400,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($buyer)
        ->get(route('orders.checkout-page', $order))
        ->assertOk()
        ->assertSee('Peserta Checkout')
        ->assertSee('Jalan Peserta 20')
        ->assertSee('Kota Peserta')
        ->assertSee('Rp20.400');
});

it('does not allow another buyer or a vendor to open a direct-order checkout', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $otherBuyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createCheckoutPageVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 1,
        'packaging_details' => 'Kraft laminasi.',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 1000,
        'total_amount' => 1020,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
    ]);

    $this->actingAs($otherBuyer)
        ->get(route('orders.checkout-page', $order))
        ->assertNotFound();

    $this->actingAs($vendor)
        ->get(route('orders.checkout-page', $order))
        ->assertForbidden();
});
