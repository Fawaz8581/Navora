<?php

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConversationVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
    ]);

    return $vendor;
}

function createConversationOrder(User $buyer, User $vendor, array $attributes = []): Order
{
    return Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 100,
        'packaging_details' => 'Kraft laminasi dengan zipper.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_WAITING,
        'payment_status' => 'awaiting_quote',
        'quote_status' => 'awaiting_vendor',
        ...$attributes,
    ]);
}

it('shares persisted quote messages between the requesting UMKM and assigned vendor', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createConversationVendor();

    $response = $this->actingAs($buyer)
        ->postJson(route('orders.quote'), [
            'vendor_user_id' => $vendor->id,
            'product_name' => 'Pouch kopi 200 g',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi dengan zipper.',
            'target_unit_price' => 1250,
            'notes' => 'Mohon opsi sampel sebelum produksi.',
        ])
        ->assertCreated()
        ->assertJsonPath('order.is_quote_request', true)
        ->assertJsonPath('order.target_unit_price', 1250)
        ->assertJsonPath('order.delivery_address', null)
        ->assertJsonPath('order.delivery_contact_phone', null);

    $order = Order::query()->findOrFail($response->json('order.id'));

    $this->actingAs($buyer)
        ->getJson(route('orders.messages.index', $order))
        ->assertOk()
        ->assertJsonPath('messages.0.is_mine', true)
        ->assertJsonPath('messages.0.message', "Permintaan penawaran untuk Pouch kopi 200 g sejumlah 500 pcs.\nSpesifikasi: Kraft laminasi dengan zipper.\nTarget harga: Rp1.250 per pcs.\nMohon opsi sampel sebelum produksi.");

    $this->actingAs($vendor)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('orders.0.id', $order->id)
        ->assertJsonPath('orders.0.is_quote_request', true);

    $this->actingAs($vendor)
        ->postJson(route('orders.messages.store', $order), [
            'message' => 'Kami bisa siapkan sampel. Berapa ukuran pouch yang dibutuhkan?',
        ])
        ->assertCreated()
        ->assertJsonPath('message.is_mine', true);

    $this->actingAs($buyer)
        ->getJson(route('orders.messages.index', $order))
        ->assertOk()
        ->assertJsonPath('messages.1.sender_name', $vendor->name)
        ->assertJsonPath('messages.1.is_mine', false)
        ->assertJsonPath('messages.1.message', 'Kami bisa siapkan sampel. Berapa ukuran pouch yang dibutuhkan?');

    expect($order->messages()->count())->toBe(2);
});

it('allows only the UMKM requester and assigned vendor to access a conversation', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createConversationVendor();
    $otherBuyer = User::factory()->create(['role' => 'umkm']);
    $otherVendor = createConversationVendor();
    $order = createConversationOrder($buyer, $vendor);

    $this->getJson(route('orders.messages.index', $order))
        ->assertUnauthorized();

    $this->actingAs($otherBuyer)
        ->getJson(route('orders.messages.index', $order))
        ->assertNotFound();

    $this->actingAs($otherVendor)
        ->postJson(route('orders.messages.store', $order), ['message' => 'Pesan tidak sah'])
        ->assertNotFound();

    expect($order->messages()->count())->toBe(0);
});

it('requires a non-empty chat message and enforces the message length limit', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createConversationVendor();
    $order = createConversationOrder($buyer, $vendor);

    $this->actingAs($buyer)
        ->postJson(route('orders.messages.store', $order), ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    $this->actingAs($buyer)
        ->postJson(route('orders.messages.store', $order), ['message' => str_repeat('a', 2001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    expect($order->messages()->count())->toBe(0);
});

it('publishes the vendor final offer in the shared conversation', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createConversationVendor();
    $order = createConversationOrder($buyer, $vendor);

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $order), [
            'unit_price' => 1250,
            'shipping_weight_per_unit_grams' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('order.total_amount', 127500)
        ->assertJsonPath('order.payment_status', 'awaiting_approval')
        ->assertJsonPath('order.quote_status', 'pending_buyer');

    $this->actingAs($buyer)
        ->getJson(route('orders.messages.index', $order))
        ->assertOk()
        ->assertJsonPath('messages.0.sender_name', $vendor->name)
        ->assertJsonPath('messages.0.message', 'Penawaran final: Rp1.250 per pcs; subtotal produk Rp125.000 dan biaya platform 2% (ongkir dihitung setelah memilih kurir).');

    expect($order->fresh()->unit_price)->toBe(1250)
        ->and($order->fresh()->total_amount)->toBe(127500);
});

it('reports only paid orders belonging to the signed-in vendor', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $buyer->businessProfile()->create([
        'business_name' => 'Kopi Nusantara',
        'verification_status' => 'pending',
    ]);
    $vendor = createConversationVendor();
    $otherVendor = createConversationVendor();

    createConversationOrder($buyer, $vendor, [
        'product_name' => 'Pouch lunas',
        'quantity' => 200,
        'unit_price' => 1200,
        'total_amount' => 240000,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);
    createConversationOrder($buyer, $vendor, [
        'product_name' => 'Pouch belum lunas',
        'unit_price' => 1000,
        'total_amount' => 100000,
        'payment_status' => 'pending',
    ]);
    createConversationOrder($buyer, $otherVendor, [
        'product_name' => 'Pesanan vendor lain',
        'unit_price' => 2000,
        'total_amount' => 200000,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($vendor)
        ->getJson(route('orders.vendor-income'))
        ->assertOk()
        ->assertJsonPath('summary.total_revenue', 240000)
        ->assertJsonPath('summary.completed_orders', 1)
        ->assertJsonPath('sales.0.product_name', 'Pouch lunas')
        ->assertJsonPath('sales.0.buyer_name', 'Kopi Nusantara')
        ->assertJsonPath('sales.0.total_amount', 240000)
        ->assertJsonMissing(['product_name' => 'Pouch belum lunas'])
        ->assertJsonMissing(['product_name' => 'Pesanan vendor lain']);

    $this->actingAs($buyer)
        ->getJson(route('orders.vendor-income'))
        ->assertForbidden();
});

it('shows vendor income and quote chat entry points in the workspace', function () {
    $vendor = createConversationVendor();

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee('Pendapatan & produk', false)
        ->assertSee('Rekap pendapatan')
        ->assertSee('data-order-chat-form', false)
        ->assertSee(route('orders.vendor-income'), false);
});
