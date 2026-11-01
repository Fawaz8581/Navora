<?php

use App\Models\BusinessProfile;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function vendorSubscriptionNotification(VendorSubscription $subscription): array
{
    $statusCode = '200';
    $grossAmount = number_format($subscription->amount, 2, '.', '');
    $serverKey = config('services.midtrans.server_key');

    return [
        'order_id' => $subscription->order_id,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $subscription->order_id.$statusCode.$grossAmount.$serverKey),
        'transaction_status' => 'settlement',
        'transaction_id' => 'midtrans-'.$subscription->order_id,
        'payment_type' => 'bank_transfer',
    ];
}

function createPremiumPlanVendor(string $businessName): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'business_name' => $businessName,
        'completed_at' => now(),
        'verification_status' => 'approved',
    ]);

    return $vendor;
}

it('activates the 30-day vendor premium plan only after a signed Midtrans settlement', function () {
    config([
        'services.midtrans.server_key' => 'vendor-subscription-test-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
        'services.navora.vendor_subscription_amount' => 500000,
        'services.navora.vendor_subscription_days' => 30,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'vendor-premium-token',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/vendor-premium',
        ], 201),
    ]);
    $vendor = createPremiumPlanVendor('Premium Vendor');

    $checkout = $this->actingAs($vendor)
        ->postJson(route('vendor.subscription.checkout'))
        ->assertOk()
        ->assertJsonPath('token', 'vendor-premium-token')
        ->assertJsonPath('subscription_id', 1);
    $subscription = VendorSubscription::query()->findOrFail($checkout->json('subscription_id'));

    expect($subscription->payment_status)->toBe('pending')
        ->and($subscription->amount)->toBe(500000)
        ->and($subscription->isActive())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request['transaction_details']['order_id'] === $subscription->order_id
        && $request['transaction_details']['gross_amount'] === 500000);

    $this->postJson(route('midtrans.notifications'), vendorSubscriptionNotification($subscription))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $subscription->refresh();
    expect($subscription->payment_status)->toBe('paid')
        ->and($subscription->isActive())->toBeTrue()
        ->and($subscription->starts_at->diffInDays($subscription->ends_at))->toBe(30.0);

    $this->actingAs($vendor)
        ->postJson(route('vendor.subscription.checkout'))
        ->assertConflict();
});

it('sorts active premium vendors first and reserves unlimited monthly RFQs for their subscription', function () {
    config(['services.navora.free_vendor_monthly_rfq_limit' => 10]);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $freeVendor = createPremiumPlanVendor('A Free Vendor');
    $premiumVendor = createPremiumPlanVendor('Z Premium Vendor');
    $subscription = VendorSubscription::query()->create([
        'vendor_user_id' => $premiumVendor->id,
        'order_id' => 'NAVORA-SUB-active-test',
        'amount' => 500000,
        'payment_status' => 'paid',
        'paid_at' => now(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(29),
    ]);
    expect($subscription->isActive())->toBeTrue();

    foreach ([$freeVendor, $premiumVendor] as $vendor) {
        foreach (range(1, 10) as $index) {
            Order::query()->create([
                'user_id' => $buyer->id,
                'vendor_user_id' => $vendor->id,
                'type' => Order::TYPE_DIRECT,
                'product_name' => 'Kuota '.$index,
                'quantity' => 1,
                'packaging_details' => 'Kemasan',
                'status' => Order::STATUS_WAITING,
                'payment_status' => 'awaiting_quote',
                'quote_status' => 'awaiting_vendor',
            ]);
        }
    }

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog'))
        ->assertOk()
        ->assertJsonPath('vendors.0.id', $premiumVendor->id)
        ->assertJsonPath('vendors.0.is_premium', true)
        ->assertJsonPath('vendors.1.id', $freeVendor->id)
        ->assertJsonPath('vendors.1.is_premium', false);

    $request = [
        'vendor_user_id' => $freeVendor->id,
        'product_name' => 'Permintaan tambahan',
        'quantity' => 100,
        'packaging_details' => 'Kemasan kraft',
        'delivery_address' => 'Bandung',
    ];
    $this->actingAs($buyer)
        ->postJson(route('orders.quote'), $request)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['vendor_user_id']);

    $request['vendor_user_id'] = $premiumVendor->id;
    $this->actingAs($buyer)
        ->postJson(route('orders.quote'), $request)
        ->assertCreated();
});
