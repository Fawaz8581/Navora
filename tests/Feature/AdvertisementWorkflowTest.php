<?php

use App\Models\AdCampaign;
use App\Models\AdPackage;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createVerifiedAdVendor(): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);

    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'approved',
    ]);

    return $vendor;
}

function createAdCampaign(User $vendor, array $attributes = []): AdCampaign
{
    return AdCampaign::query()->create([
        'vendor_user_id' => $vendor->id,
        'package_name' => 'Banner Global',
        'placement' => 'global',
        'width' => 1200,
        'height' => 300,
        'amount' => 50000,
        'duration_days' => 7,
        'order_id' => 'NAVORA-AD-'.fake()->uuid(),
        'payment_status' => 'pending',
        'status' => AdCampaign::STATUS_PENDING_PAYMENT,
        ...$attributes,
    ]);
}

function fakeAdImage(string $name = 'banner.png'): UploadedFile
{
    $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);

    if ($content === false) {
        throw new RuntimeException('The advertisement test image could not be decoded.');
    }

    return UploadedFile::fake()->createWithContent($name, $content);
}

function fakeAdGif(): UploadedFile
{
    $content = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);

    if ($content === false) {
        throw new RuntimeException('The advertisement test GIF could not be decoded.');
    }

    return UploadedFile::fake()->createWithContent('banner.gif', $content);
}

function midtransNotificationFor(AdCampaign $campaign, string $status = 'settlement'): array
{
    $serverKey = config('services.midtrans.server_key');
    $statusCode = '200';
    $grossAmount = number_format($campaign->amount, 2, '.', '');

    return [
        'order_id' => $campaign->order_id,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => hash('sha512', $campaign->order_id.$statusCode.$grossAmount.$serverKey),
        'transaction_status' => $status,
    ];
}

test('verified vendors can start a Midtrans checkout for an admin-configured package', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
            'token' => 'snap-token-123',
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123',
        ], 201),
    ]);
    $vendor = createVerifiedAdVendor();
    $package = AdPackage::query()->create([
        'name' => 'Banner 1200x300',
        'placement' => 'sidebar',
        'width' => 1200,
        'height' => 300,
        'price' => 50000,
        'duration_days' => 7,
        'is_active' => true,
    ]);

    $this->actingAs($vendor)
        ->get(route('vendor.advertisements.index'))
        ->assertOk()
        ->assertSee('Banner 1200x300')
        ->assertSee('Slot samping')
        ->assertSee('Rp50.000');

    $this->actingAs($vendor)
        ->post(route('vendor.advertisements.store'), [
            'ad_package_id' => $package->id,
            'amount' => 1,
        ])
        ->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123');

    $campaign = AdCampaign::query()->firstOrFail();

    expect($campaign->amount)->toBe(50000)
        ->and(substr($campaign->order_id, 0, 10))->toBe('NAVORA-AD-')
        ->and($campaign->snap_token)->toBe('snap-token-123')
        ->and($campaign->placement)->toBe('sidebar')
        ->and($campaign->status)->toBe(AdCampaign::STATUS_PENDING_PAYMENT);

    Http::assertSent(fn ($request) => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request['transaction_details']['gross_amount'] === 50000
        && $request['transaction_details']['order_id'] === $campaign->order_id);
});

test('signed Midtrans notifications confirm payment and duplicate notices do not reset campaign review state', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor);

    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_PAID)
        ->and($campaign->fresh()->payment_status)->toBe('paid')
        ->and($campaign->fresh()->paid_at)->not->toBeNull();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign))
        ->assertRedirect();

    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign))
        ->assertOk();

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_AWAITING_ASSET);
});

test('admin can approve an asset before publishing it to the homepage and workspace', function () {
    Storage::fake('local');
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'payment_status' => 'paid',
        'status' => AdCampaign::STATUS_PAID,
        'paid_at' => now(),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee($vendor->email)
        ->assertSee('Lunas · menunggu persetujuan');

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign))
        ->assertRedirect();

    $this->actingAs($vendor)
        ->post(route('vendor.advertisements.asset', $campaign), [
            'asset' => fakeAdGif(),
        ])
        ->assertRedirect(route('vendor.advertisements.index'))
        ->assertSessionHas('status');

    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED);
    Storage::disk('local')->assertExists($campaign->asset_path);

    $this->actingAs($admin)
        ->get(route('admin.customize.campaigns.asset', $campaign))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.publish', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaign::STATUS_ACTIVE)
        ->and($campaign->starts_at)->not->toBeNull()
        ->and($campaign->ends_at->isSameDay($campaign->starts_at->copy()->addDays(7)))->toBeTrue();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $campaign), false);
    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $campaign), false);
    $this->get(route('advertisements.asset', $campaign))
        ->assertOk()
        ->assertHeader('content-type', 'image/gif');
});

test('active sidebar campaigns appear on the homepage and workspace', function () {
    $vendor = createVerifiedAdVendor();
    $buyer = User::factory()->create(['role' => 'umkm']);
    BusinessProfile::factory()->for($buyer)->create(['completed_at' => now()]);
    $bannerCampaign = createAdCampaign($vendor, [
        'placement' => 'global',
        'status' => AdCampaign::STATUS_ACTIVE,
        'payment_status' => 'paid',
        'asset_path' => 'advertisements/banner/top.png',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
    ]);
    $campaign = createAdCampaign($vendor, [
        'placement' => 'sidebar',
        'status' => AdCampaign::STATUS_ACTIVE,
        'payment_status' => 'paid',
        'asset_path' => 'advertisements/sidebar/banner.png',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $bannerCampaign), false)
        ->assertSee('navora-ad-sidebar')
        ->assertSee(route('advertisements.asset', $campaign), false);

    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $bannerCampaign), false);

    $this->actingAs($buyer)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $bannerCampaign), false)
        ->assertSee('navora-ad-sidebar')
        ->assertSee(route('advertisements.asset', $campaign), false);
});

test('advertising controls reject unapproved vendors, non-admins, invalid notifications, and cross-vendor assets', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => 'pending',
    ]);
    $otherVendor = createVerifiedAdVendor();
    $differentVendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($otherVendor, [
        'status' => AdCampaign::STATUS_AWAITING_ASSET,
        'payment_status' => 'paid',
        'paid_at' => now(),
    ]);
    $umkm = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($vendor)
        ->get(route('vendor.advertisements.index'))
        ->assertForbidden();

    $this->actingAs($umkm)
        ->get(route('admin.customize.index'))
        ->assertForbidden();

    $this->postJson(route('midtrans.notifications'), [
        ...midtransNotificationFor($campaign),
        'signature_key' => 'invalid-signature',
    ])->assertForbidden();

    Storage::fake('local');
    $this->actingAs($differentVendor)
        ->post(route('vendor.advertisements.asset', $campaign), [
            'asset' => fakeAdImage(),
        ])
        ->assertNotFound();

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_AWAITING_ASSET)
        ->and(Storage::disk('local')->allFiles('advertisements'))->toBe([]);
});

test('admins can configure package prices and non-admins cannot change packages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $umkm = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($admin)
        ->post(route('admin.customize.packages.store'), [
            'name' => 'Header 1200x300',
            'width' => 1200,
            'height' => 300,
            'price' => 75000,
            'duration_days' => 14,
            'placement' => 'sidebar',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $package = AdPackage::query()->firstOrFail();
    expect($package->price)->toBe(75000)
        ->and($package->placement)->toBe('sidebar')
        ->and($package->duration_days)->toBe(14)
        ->and($package->is_active)->toBeTrue();

    $this->actingAs($umkm)
        ->post(route('admin.customize.packages.store'), [
            'name' => 'Unauthorized',
            'width' => 1200,
            'height' => 300,
            'price' => 1000,
            'duration_days' => 1,
        ])
        ->assertForbidden();
});
