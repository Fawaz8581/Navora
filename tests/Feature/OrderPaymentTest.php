<?php

use App\Jobs\AdvanceSimulatedOrder;
use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createPaymentVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
        'business_address' => 'Jalan Industri No. 10, Bandung',
        'rajaongkir_area_id' => '100',
        'rajaongkir_area_name' => 'Coblong, Kota Bandung',
    ]);

    return $vendor;
}

/**
 * @return array{order_id: string, status_code: string, gross_amount: string, signature_key: string, transaction_status: string, transaction_id: string, payment_type: string}
 */
function orderPaymentNotification(string $orderId, int $amount): array
{
    $statusCode = '200';
    $grossAmount = number_format($amount, 2, '.', '');
    $serverKey = config('services.midtrans.server_key');

    return [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey),
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-'.$orderId,
        'payment_type' => 'bank_transfer',
    ];
}

it('requires a final vendor price and confirms a direct order only after a signed Midtrans payment', function () {
    config([
        'services.midtrans.server_key' => 'order-payment-test-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'order-snap-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/order-snap-token',
        ], 201),
    ]);
    Queue::fake([AdvanceSimulatedOrder::class]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createPaymentVendor();

    $orderResponse = $this->actingAs($buyer)
        ->postJson(route('orders.quote'), [
            'vendor_user_id' => $vendor->id,
            'product_name' => 'Pouch kopi',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi dengan zipper.',
            'target_unit_price' => 150,
        ])
        ->assertCreated()
        ->assertJsonPath('order.payment_status', 'awaiting_quote')
        ->assertJsonPath('order.target_unit_price', 150)
        ->assertJsonPath('order.delivery_address', null);
    $orderId = $orderResponse->json('order.id');

    $this->actingAs($buyer)
        ->postJson(route('orders.checkout', $orderId))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $orderId), [
            'unit_price' => 180,
            'shipping_weight_per_unit_grams' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('order.unit_price', 180)
        ->assertJsonPath('order.total_amount', 91800)
        ->assertJsonPath('order.payment_status', 'awaiting_approval')
        ->assertJsonPath('order.quote_status', 'pending_buyer');

    $this->actingAs($buyer)
        ->postJson(route('orders.quote-decision', $orderId), ['decision' => 'accept'])
        ->assertOk()
        ->assertJsonPath('order.quote_status', 'accepted')
        ->assertJsonPath('order.payment_status', 'pending');

    $this->actingAs($buyer)
        ->putJson(route('orders.delivery-details', $orderId), [
            'delivery_contact_name' => 'Pembeli Navora',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
        ])
        ->assertOk()
        ->assertJsonPath('order.delivery_contact_name', 'Pembeli Navora')
        ->assertJsonPath('order.can_select_shipping', true);

    Order::query()->findOrFail($orderId)->forceFill([
        'shipping_amount' => 0,
        'shipping_courier' => 'test',
        'shipping_service' => 'TEST',
    ])->save();

    $this->actingAs($buyer)
        ->postJson(route('orders.checkout', $orderId))
        ->assertOk()
        ->assertJsonPath('token', 'order-snap-token');

    $order = Order::query()->findOrFail($orderId);
    expect($order->payment_order_id)->toStartWith('NAVORA-ORDER-')
        ->and($order->snap_token)->toBe('order-snap-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request['transaction_details']['order_id'] === $order->payment_order_id
        && $request['transaction_details']['gross_amount'] === 91800
        && $request['customer_details']['email'] === $buyer->email);

    $notification = orderPaymentNotification($order->payment_order_id, 91800);
    $this->postJson(route('midtrans.notifications'), [
        ...$notification,
        'signature_key' => 'invalid',
    ])->assertForbidden();

    expect($order->fresh()->payment_status)->toBe('pending');

    $this->postJson(route('midtrans.notifications'), $notification)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->midtrans_transaction_id)->toBe('midtrans-'.$order->payment_order_id)
        ->and($order->fresh()->payment_type)->toBe('bank_transfer');

    $this->actingAs($vendor)
        ->getJson(route('orders.vendor-income'))
        ->assertOk()
        ->assertJsonPath('summary.total_revenue', 90000)
        ->assertJsonPath('summary.completed_orders', 1)
        ->assertJsonPath('sales.0.product_name', 'Pouch kopi')
        ->assertJsonPath('sales.0.total_amount', 90000);

    $this->actingAs($vendor)
        ->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('order.payment_receipt.transaction_id', 'midtrans-'.$order->payment_order_id)
        ->assertJsonPath('order.payment_receipt.payment_type', 'bank_transfer');

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_PROCESSING])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_PROCESSING)
        ->assertJsonPath('order.is_simulated', false);

    Queue::assertNothingPushed();
});

it('requires each group-order participant to pay their own share before the order is paid', function () {
    config([
        'services.midtrans.server_key' => 'group-payment-test-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::sequence()
            ->push([
                'token' => 'organizer-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/organizer',
            ], 201)
            ->push([
                'token' => 'participant-snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/participant',
            ], 201),
    ]);
    $organizer = User::factory()->create(['role' => 'umkm']);
    $participant = User::factory()->create(['role' => 'umkm']);
    $vendor = createPaymentVendor();

    $creation = $this->actingAs($organizer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_GROUP,
            'product_name' => 'Pouch standar',
            'quantity' => 100,
            'initial_quantity' => 40,
            'packaging_details' => 'Pouch standar berbahan kraft.',
            'delivery_contact_name' => 'Penyelenggara',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
            'vendor_user_id' => $vendor->id,
        ])
        ->assertCreated();
    $orderId = $creation->json('order.id');

    $this->actingAs($participant)
        ->postJson(route('orders.join', $orderId), [
            'quantity' => 60,
            'delivery_contact_name' => 'Peserta',
            'delivery_contact_phone' => '+6281234567891',
            'delivery_address' => 'Jalan Melati 12, Bandung',
            'delivery_area_id' => '201',
            'delivery_area_name' => 'Sukajadi, Kota Bandung',
        ])
        ->assertOk();

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $orderId), [
            'unit_price' => 200,
            'shipping_weight_per_unit_grams' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('order.total_amount', 20400)
        ->assertJsonPath('order.payment_status', 'awaiting_approval')
        ->assertJsonPath('order.participants.0.payment_amount', 8160)
        ->assertJsonPath('order.participants.1.payment_amount', 12240);

    $this->actingAs($organizer)
        ->postJson(route('orders.quote-decision', $orderId), ['decision' => 'accept'])
        ->assertOk()
        ->assertJsonPath('order.payment_status', 'awaiting_approval');

    $this->actingAs($organizer)
        ->postJson(route('orders.checkout', $orderId))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    $this->actingAs($participant)
        ->postJson(route('orders.quote-decision', $orderId), ['decision' => 'accept'])
        ->assertOk()
        ->assertJsonPath('order.quote_status', 'accepted')
        ->assertJsonPath('order.payment_status', 'pending');

    Order::query()->findOrFail($orderId)->participants()->update([
        'shipping_amount' => 0,
        'shipping_courier' => 'test',
        'shipping_service' => 'TEST',
    ]);

    $this->actingAs($organizer)
        ->postJson(route('orders.checkout', $orderId))
        ->assertOk()
        ->assertJsonPath('token', 'organizer-snap-token');

    $order = Order::query()->findOrFail($orderId);
    $organizerPaymentId = $order->participants()->where('user_id', $organizer->id)->value('payment_order_id');

    $this->actingAs($organizer)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_CANCELLED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_CANCELLED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->postJson(
        route('midtrans.notifications'),
        orderPaymentNotification($organizerPaymentId, 8160),
    )->assertOk();

    expect($order->fresh()->payment_status)->toBe('pending');

    $this->actingAs($participant)
        ->postJson(route('orders.checkout', $orderId))
        ->assertOk()
        ->assertJsonPath('token', 'participant-snap-token');

    $participantPaymentId = $order->participants()->where('user_id', $participant->id)->value('payment_order_id');
    $this->postJson(
        route('midtrans.notifications'),
        orderPaymentNotification($participantPaymentId, 12240),
    )->assertOk();

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->participants()->where('payment_status', 'paid')->count())->toBe(2);

    Http::assertSent(fn (Request $request): bool => $request['transaction_details']['gross_amount'] === 8160);
    Http::assertSent(fn (Request $request): bool => $request['transaction_details']['gross_amount'] === 12240);
});

it('allows a buyer to reject a quote and receive a revised offer before paying', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createPaymentVendor();

    $orderId = $this->actingAs($buyer)
        ->postJson(route('orders.quote'), [
            'vendor_user_id' => $vendor->id,
            'product_name' => 'Pouch kopi',
            'quantity' => 100,
            'packaging_details' => 'Kraft laminasi.',
            'delivery_address' => 'Bandung.',
        ])
        ->assertCreated()
        ->json('order.id');

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $orderId), [
            'unit_price' => 200,
            'shipping_weight_per_unit_grams' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('order.can_set_price', false);

    $this->actingAs($buyer)
        ->postJson(route('orders.quote-decision', $orderId), ['decision' => 'reject'])
        ->assertOk()
        ->assertJsonPath('order.quote_status', 'rejected')
        ->assertJsonPath('order.payment_status', 'awaiting_quote')
        ->assertJsonPath('order.unit_price', null);

    $this->actingAs($buyer)
        ->postJson(route('orders.checkout', $orderId))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment']);

    $this->actingAs($vendor)
        ->postJson(route('orders.price', $orderId), [
            'unit_price' => 175,
            'shipping_weight_per_unit_grams' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('order.unit_price', 175)
        ->assertJsonPath('order.quote_status', 'pending_buyer');
});

it('refreshes Midtrans status from the server and records a verified receipt', function () {
    config([
        'services.midtrans.server_key' => 'refresh-payment-test-key',
        'services.midtrans.api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createPaymentVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 10,
        'packaging_details' => 'Kraft.',
        'delivery_address' => 'Bandung.',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 100,
        'total_amount' => 1000,
        'payment_status' => 'pending',
        'quote_status' => 'accepted',
        'payment_order_id' => 'NAVORA-ORDER-refresh',
    ]);
    Http::fake([
        'https://api.sandbox.midtrans.com/v2/NAVORA-ORDER-refresh/status' => Http::response([
            'status_code' => '200',
            'order_id' => $order->payment_order_id,
            'gross_amount' => '1000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'verified-transaction-1',
            'payment_type' => 'qris',
        ]),
    ]);

    $this->actingAs($vendor)
        ->postJson(route('orders.payment-refresh', $order))
        ->assertOk()
        ->assertJsonPath('payment_status', 'paid')
        ->assertJsonPath('order.payment_receipt.transaction_id', 'verified-transaction-1')
        ->assertJsonPath('order.payment_receipt.payment_type', 'qris');

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->midtrans_transaction_status)->toBe('settlement');
});

it('rejects price changes from a vendor who is not assigned to the order', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $assignedVendor = createPaymentVendor();
    $otherVendor = createPaymentVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $assignedVendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch',
        'quantity' => 10,
        'packaging_details' => 'Kraft.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_WAITING,
        'payment_status' => 'awaiting_quote',
    ]);

    $this->actingAs($otherVendor)
        ->postJson(route('orders.price', $order), ['unit_price' => 100])
        ->assertNotFound();

    expect($order->fresh()->unit_price)->toBeNull()
        ->and($order->fresh()->payment_status)->toBe('awaiting_quote');
});

it('rejects a Midtrans notification with a mismatched amount', function () {
    config(['services.midtrans.server_key' => 'amount-check-test-key']);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch',
        'quantity' => 10,
        'packaging_details' => 'Kraft.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_WAITING,
        'unit_price' => 100,
        'total_amount' => 1000,
        'payment_status' => 'pending',
        'payment_order_id' => 'NAVORA-ORDER-test-amount',
    ]);

    $this->postJson(route('midtrans.notifications'), orderPaymentNotification($order->payment_order_id, 999))
        ->assertUnprocessable();

    expect($order->fresh()->payment_status)->toBe('pending');
});
