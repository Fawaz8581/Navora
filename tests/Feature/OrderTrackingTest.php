<?php

use App\Jobs\AdvanceSimulatedOrder;
use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createVerifiedOrderVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
    ]);

    return $vendor;
}

it('creates a direct order and records its initial tracking status', function () {
    $buyer = User::factory()->create();
    $vendor = createVerifiedOrderVendor();

    $this->actingAs($buyer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_DIRECT,
            'product_name' => 'Pouch kopi khusus',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi, zipper, ukuran 200 gram.',
            'delivery_contact_name' => 'Pembeli Navora',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
            'vendor_user_id' => $vendor->id,
        ])
        ->assertCreated()
        ->assertJsonPath('order.type_label', 'Pesanan langsung')
        ->assertJsonPath('order.status', Order::STATUS_WAITING)
        ->assertJsonPath('order.quote_status', 'awaiting_vendor')
        ->assertJsonPath('order.delivery_address', 'Jalan Melati 10, Bandung');

    $order = Order::query()->sole();

    expect($order->type)->toBe(Order::TYPE_DIRECT)
        ->and($order->quantity)->toBe(500)
        ->and($order->statusHistory()->sole()->status)->toBe(Order::STATUS_WAITING);
});

it('requires a delivery address before creating a direct order', function () {
    $buyer = User::factory()->create();
    $vendor = createVerifiedOrderVendor();

    $this->actingAs($buyer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_DIRECT,
            'product_name' => 'Pouch kopi khusus',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi, zipper.',
            'vendor_user_id' => $vendor->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['delivery_address']);

    expect(Order::query()->count())->toBe(0);
});

it('creates a group order and starts processing when its target is reached', function () {
    $organizer = User::factory()->create();
    $participant = User::factory()->create();
    $vendor = createVerifiedOrderVendor();

    $creation = $this->actingAs($organizer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_GROUP,
            'product_name' => 'Pouch standar 200 gram',
            'quantity' => 100,
            'initial_quantity' => 40,
            'packaging_details' => 'Pouch standar berbahan kraft dengan zipper.',
            'delivery_contact_name' => 'Penyelenggara',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
            'vendor_user_id' => $vendor->id,
        ])
        ->assertCreated()
        ->assertJsonPath('order.type', Order::TYPE_GROUP)
        ->assertJsonPath('order.status', Order::STATUS_WAITING)
        ->assertJsonPath('order.committed_quantity', 40)
        ->assertJsonPath('order.remaining_quantity', 60);

    $orderId = $creation->json('order.id');

    $this->actingAs($organizer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('open_group_count', 1)
        ->assertJsonCount(0, 'orders');

    $this->actingAs($organizer)
        ->getJson(route('orders.index', ['type' => Order::TYPE_GROUP]))
        ->assertOk()
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.type', Order::TYPE_GROUP);

    $this->actingAs($participant)
        ->postJson(route('orders.join', $orderId), [
            'quantity' => 60,
            'delivery_contact_name' => 'Peserta Navora',
            'delivery_contact_phone' => '+6281234567891',
            'delivery_address' => 'Jalan Melati 12, Bandung',
            'delivery_area_id' => '201',
            'delivery_area_name' => 'Sukajadi, Kota Bandung',
        ])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_WAITING)
        ->assertJsonPath('order.committed_quantity', 100)
        ->assertJsonPath('order.remaining_quantity', 0);

    $this->actingAs($participant)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('open_group_count', 0);

    $order = Order::query()->findOrFail($orderId);

    expect($order->status)->toBe(Order::STATUS_WAITING)
        ->and($order->participants()->count())->toBe(2)
        ->and($order->statusHistory()->orderBy('id')->pluck('status')->all())->toBe([
            Order::STATUS_WAITING,
            Order::STATUS_WAITING,
        ]);
});

it('rejects group contributions that exceed the remaining target', function () {
    $organizer = User::factory()->create();
    $participant = User::factory()->create();

    $order = Order::query()->create([
        'user_id' => $organizer->id,
        'type' => Order::TYPE_GROUP,
        'product_name' => 'Pouch standar',
        'quantity' => 100,
        'packaging_details' => 'Pouch standar.',
        'status' => Order::STATUS_WAITING,
    ]);
    $order->participants()->create(['user_id' => $organizer->id, 'quantity' => 40]);

    $this->actingAs($participant)
        ->postJson(route('orders.join', $order), [
            'quantity' => 61,
            'delivery_contact_name' => 'Peserta Navora',
            'delivery_contact_phone' => '+6281234567891',
            'delivery_address' => 'Jalan Melati 12, Bandung',
            'delivery_area_id' => '201',
            'delivery_area_name' => 'Sukajadi, Kota Bandung',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);

    expect($order->participants()->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(Order::STATUS_WAITING);
});

it('does not expose another users direct order', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $order = Order::query()->create([
        'user_id' => $owner->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pesanan pribadi',
        'quantity' => 10,
        'packaging_details' => 'Spesifikasi khusus.',
        'delivery_address' => 'Alamat rahasia',
        'status' => Order::STATUS_WAITING,
    ]);

    $this->actingAs($otherUser)
        ->getJson(route('orders.show', $order))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_CANCELLED])
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->postJson(route('orders.join', $order), ['quantity' => 1])
        ->assertNotFound();
});

it('creates a real order quote request for a verified vendor', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
    ]);
    Queue::fake([AdvanceSimulatedOrder::class]);

    $response = $this->actingAs($buyer)
        ->postJson(route('orders.quote'), [
            'vendor_user_id' => $vendor->id,
            'product_name' => 'Pouch kopi standar',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi dengan zipper.',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'notes' => 'Mohon opsi sampel.',
        ])
        ->assertCreated()
        ->assertJsonPath('order.type', Order::TYPE_DIRECT)
        ->assertJsonPath('order.status', Order::STATUS_WAITING)
        ->assertJsonPath('order.is_simulated', false)
        ->assertJsonPath('order.payment_status', 'awaiting_quote')
        ->assertJsonPath('order.vendor_name', $vendor->businessProfile->business_name);

    $order = Order::query()->sole();

    expect($order->user_id)->toBe($buyer->id)
        ->and($order->vendor_user_id)->toBe($vendor->id)
        ->and($order->is_simulated)->toBeFalse()
        ->and($order->statusHistory()->orderBy('id')->pluck('status')->all())->toBe([
            Order::STATUS_WAITING,
        ]);

    Queue::assertNothingPushed();

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $response->json('order.id')), ['status' => Order::STATUS_PRODUCTION])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects quote requests to unverified vendors without creating orders', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'pending',
    ]);
    Queue::fake([AdvanceSimulatedOrder::class]);

    $this->actingAs($buyer)
        ->postJson(route('orders.quote'), [
            'vendor_user_id' => $vendor->id,
            'product_name' => 'Pouch kopi standar',
            'quantity' => 500,
            'packaging_details' => 'Kraft laminasi.',
            'delivery_address' => 'Jalan Melati 10, Bandung',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vendor_user_id']);

    expect(Order::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('advances simulated stages in order and ignores stale jobs and real orders', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $simulatedOrder = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi simulasi',
        'quantity' => 500,
        'packaging_details' => 'Kemasan standar.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_PROCESSING,
        'is_simulated' => true,
    ]);
    $simulatedOrder->statusHistory()->create([
        'user_id' => null,
        'status' => Order::STATUS_PROCESSING,
        'note' => 'Simulasi otomatis dimulai.',
    ]);
    $realOrder = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi nyata',
        'quantity' => 500,
        'packaging_details' => 'Kemasan standar.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_PROCESSING,
        'is_simulated' => false,
    ]);
    Queue::fake([AdvanceSimulatedOrder::class]);

    (new AdvanceSimulatedOrder($simulatedOrder->id, Order::STATUS_PROCESSING))->handle();
    expect($simulatedOrder->fresh()->status)->toBe(Order::STATUS_PRODUCTION);

    (new AdvanceSimulatedOrder($simulatedOrder->id, Order::STATUS_PROCESSING))->handle();
    expect($simulatedOrder->fresh()->status)->toBe(Order::STATUS_PRODUCTION);

    (new AdvanceSimulatedOrder($simulatedOrder->id, Order::STATUS_PRODUCTION))->handle();
    expect($simulatedOrder->fresh()->status)->toBe(Order::STATUS_SHIPPED);

    (new AdvanceSimulatedOrder($simulatedOrder->id, Order::STATUS_SHIPPED))->handle();
    expect($simulatedOrder->fresh()->status)->toBe(Order::STATUS_RECEIVED)
        ->and($simulatedOrder->statusHistory()->orderBy('id')->pluck('status')->all())->toBe([
            Order::STATUS_PROCESSING,
            Order::STATUS_PRODUCTION,
            Order::STATUS_SHIPPED,
            Order::STATUS_RECEIVED,
        ]);

    (new AdvanceSimulatedOrder($realOrder->id, Order::STATUS_PROCESSING))->handle();
    expect($realOrder->fresh()->status)->toBe(Order::STATUS_PROCESSING);

    Queue::assertPushed(AdvanceSimulatedOrder::class, fn (AdvanceSimulatedOrder $job): bool => $job->orderId === $simulatedOrder->id
        && $job->expectedStatus === Order::STATUS_SHIPPED);
});

it('catches up simulated delivery stages when tracking is opened without a queue worker', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    Queue::fake([AdvanceSimulatedOrder::class]);
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi simulasi',
        'quantity' => 500,
        'packaging_details' => 'Kemasan standar.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_PROCESSING,
        'is_simulated' => true,
    ]);
    $history = $order->statusHistory()->create([
        'user_id' => null,
        'status' => Order::STATUS_PROCESSING,
        'note' => 'Simulasi otomatis dimulai.',
    ]);
    $history->created_at = now()->subSeconds(61);
    $history->updated_at = $history->created_at;
    $history->save();

    $this->actingAs($buyer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('orders.0.status', Order::STATUS_RECEIVED)
        ->assertJsonPath('orders.0.history.1.status', Order::STATUS_PRODUCTION)
        ->assertJsonPath('orders.0.history.1.note', 'Simulasi: pesanan sedang diproduksi oleh vendor.')
        ->assertJsonPath('orders.0.history.2.status', Order::STATUS_SHIPPED)
        ->assertJsonPath('orders.0.history.2.note', 'Simulasi: pesanan sedang dalam perjalanan ke alamat pengiriman.')
        ->assertJsonPath('orders.0.history.3.status', Order::STATUS_RECEIVED)
        ->assertJsonPath('orders.0.history.3.note', 'Simulasi: pesanan ditandai telah diterima.');

    expect($order->fresh()->status)->toBe(Order::STATUS_RECEIVED)
        ->and($order->statusHistory()->orderBy('id')->pluck('status')->all())->toBe([
            Order::STATUS_PROCESSING,
            Order::STATUS_PRODUCTION,
            Order::STATUS_SHIPPED,
            Order::STATUS_RECEIVED,
        ]);
});

it('waits for the automatic simulation interval before advancing the next stage', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    Queue::fake([AdvanceSimulatedOrder::class]);
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi simulasi',
        'quantity' => 500,
        'packaging_details' => 'Kemasan standar.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_PROCESSING,
        'is_simulated' => true,
    ]);
    $history = $order->statusHistory()->create([
        'user_id' => null,
        'status' => Order::STATUS_PROCESSING,
    ]);
    $history->created_at = now()->subSeconds(19);
    $history->updated_at = $history->created_at;
    $history->save();

    $this->actingAs($buyer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('orders.0.status', Order::STATUS_PROCESSING);

    $history->created_at = now()->subSeconds(Order::SIMULATION_STAGE_DELAY_SECONDS + 1);
    $history->updated_at = $history->created_at;
    $history->save();

    $this->actingAs($buyer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('orders.0.status', Order::STATUS_PRODUCTION);

    Queue::assertPushed(AdvanceSimulatedOrder::class, fn (AdvanceSimulatedOrder $job): bool => $job->orderId === $order->id
        && $job->expectedStatus === Order::STATUS_PRODUCTION);
});

it('does not start order tracking until an assigned verified vendor has been paid', function () {
    $buyer = User::factory()->create();
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
    ]);
    Queue::fake([AdvanceSimulatedOrder::class]);

    $creation = $this->actingAs($buyer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_DIRECT,
            'product_name' => 'Pouch kopi',
            'quantity' => 200,
            'packaging_details' => 'Kraft dengan zipper.',
            'delivery_contact_name' => 'Pembeli Navora',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Merdeka 2, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
            'vendor_user_id' => $vendor->id,
        ])
        ->assertCreated();
    $orderId = $creation->json('order.id');

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_SHIPPED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($vendor)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_PRODUCTION])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $orderId), ['status' => Order::STATUS_RECEIVED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    Queue::assertNothingPushed();

    expect(OrderStatusHistory::query()->where('order_id', $orderId)->orderBy('id')->pluck('status')->all())->toBe([
        Order::STATUS_WAITING,
    ]);
});

it('does not allow the buyer to confirm an order without an assigned vendor', function () {
    $buyer = User::factory()->create();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch khusus',
        'quantity' => 100,
        'packaging_details' => 'Kraft dengan zipper.',
        'delivery_address' => 'Jalan Merdeka 2, Bandung',
        'status' => Order::STATUS_WAITING,
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('order.allowed_transitions', [
            ['status' => Order::STATUS_CANCELLED, 'label' => 'Dibatalkan'],
        ]);

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($order->fresh()->status)->toBe(Order::STATUS_WAITING)
        ->and($order->fresh()->is_simulated)->toBeFalse();
});

it('allows the UMKM buyer to confirm receipt after the vendor ships a paid order', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = createVerifiedOrderVendor();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'vendor_user_id' => $vendor->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch kopi',
        'quantity' => 100,
        'packaging_details' => 'Kraft.',
        'delivery_address' => 'Bandung.',
        'status' => Order::STATUS_SHIPPED,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_RECEIVED])
        ->assertOk()
        ->assertJsonPath('order.status', Order::STATUS_RECEIVED)
        ->assertJsonPath('order.history.0.note', 'UMKM mengonfirmasi pesanan telah diterima.');

    expect($order->fresh()->status)->toBe(Order::STATUS_RECEIVED);
});

it('rejects an unverified vendor and a vendor role from creating or joining orders', function () {
    $buyer = User::factory()->create();
    $unverifiedVendor = User::factory()->create(['role' => 'vendor']);

    $this->actingAs($buyer)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_DIRECT,
            'product_name' => 'Pouch',
            'quantity' => 10,
            'packaging_details' => 'Kraft.',
            'delivery_contact_name' => 'Pembeli Navora',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Merdeka 2, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
            'vendor_user_id' => $unverifiedVendor->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vendor_user_id']);

    $group = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_GROUP,
        'product_name' => 'Pouch standar',
        'quantity' => 100,
        'packaging_details' => 'Pouch standar.',
        'status' => Order::STATUS_WAITING,
    ]);

    $this->actingAs($unverifiedVendor)
        ->postJson(route('orders.store'), [
            'type' => Order::TYPE_DIRECT,
            'product_name' => 'Pouch',
            'quantity' => 10,
            'packaging_details' => 'Kraft.',
            'delivery_address' => 'Jalan Merdeka 2, Bandung',
        ])
        ->assertForbidden();

    $this->actingAs($unverifiedVendor)
        ->postJson(route('orders.join', $group), [
            'quantity' => 10,
            'delivery_contact_name' => 'Vendor',
            'delivery_contact_phone' => '+6281234567890',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'delivery_area_id' => '200',
            'delivery_area_name' => 'Coblong, Kota Bandung',
        ])
        ->assertForbidden();
});

it('requires authentication to access the orders endpoint', function () {
    $this->getJson(route('orders.index'))
        ->assertUnauthorized();
});

it('allows the order owner to cancel before shipment and rejects later status changes', function () {
    $buyer = User::factory()->create();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch khusus',
        'quantity' => 20,
        'packaging_details' => 'Ukuran khusus.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => Order::STATUS_WAITING,
    ]);

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_CANCELLED])
        ->assertOk()
        ->assertJsonPath('order.status_label', 'Dibatalkan');

    $this->actingAs($buyer)
        ->patchJson(route('orders.status', $order), ['status' => Order::STATUS_PROCESSING])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($order->refresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($order->statusHistory()->where('status', Order::STATUS_CANCELLED)->exists())->toBeTrue();
});

it('labels an unknown stored status without losing access to the order detail', function () {
    $buyer = User::factory()->create();
    $order = Order::query()->create([
        'user_id' => $buyer->id,
        'type' => Order::TYPE_DIRECT,
        'product_name' => 'Pouch khusus',
        'quantity' => 20,
        'packaging_details' => 'Ukuran khusus.',
        'delivery_address' => 'Jalan Melati 10, Bandung',
        'status' => 'status_lama',
    ]);

    $this->actingAs($buyer)
        ->getJson(route('orders.show', $order))
        ->assertOk()
        ->assertJsonPath('order.status_label', 'Status tidak dikenal')
        ->assertJsonPath('order.allowed_transitions', []);
});

it('returns no example orders for a signed-in account without orders', function () {
    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonPath('orders', [])
        ->assertJsonPath('vendors', []);
});

it('provides older orders on a following page instead of silently omitting them', function () {
    $buyer = User::factory()->create();

    foreach (range(1, 51) as $number) {
        Order::query()->create([
            'user_id' => $buyer->id,
            'type' => Order::TYPE_DIRECT,
            'product_name' => "Pesanan {$number}",
            'quantity' => 10,
            'packaging_details' => 'Spesifikasi khusus.',
            'delivery_address' => 'Jalan Melati 10, Bandung',
            'status' => Order::STATUS_WAITING,
        ]);
    }

    $this->actingAs($buyer)
        ->getJson(route('orders.index'))
        ->assertOk()
        ->assertJsonCount(50, 'orders')
        ->assertJsonPath('orders.0.product_name', 'Pesanan 51')
        ->assertJsonPath('next_page_url', route('orders.index', ['page' => 2]));

    $this->actingAs($buyer)
        ->getJson(route('orders.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'orders')
        ->assertJsonPath('orders.0.product_name', 'Pesanan 1');
});

it('preserves a group order history when a participant deletes their account', function () {
    $organizer = User::factory()->create();
    $participant = User::factory()->create();
    $order = Order::query()->create([
        'user_id' => $organizer->id,
        'type' => Order::TYPE_GROUP,
        'product_name' => 'Pouch standar',
        'quantity' => 100,
        'packaging_details' => 'Pouch standar.',
        'status' => Order::STATUS_WAITING,
    ]);
    $order->participants()->create(['user_id' => $organizer->id, 'quantity' => 20]);
    $order->participants()->create(['user_id' => $participant->id, 'quantity' => 30]);
    $history = $order->statusHistory()->create([
        'user_id' => $participant->id,
        'status' => Order::STATUS_WAITING,
        'note' => 'Peserta bergabung.',
    ]);

    $this->actingAs($participant)
        ->delete(route('account.destroy'), ['password' => 'password'])
        ->assertRedirect(route('login'));

    expect($order->participants()->where('user_id', $participant->id)->exists())->toBeFalse()
        ->and($history->refresh()->user_id)->toBeNull()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});
