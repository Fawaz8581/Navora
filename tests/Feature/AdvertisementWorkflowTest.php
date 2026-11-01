<?php

use App\Models\AdCampaign;
use App\Models\AdPackage;
use App\Models\AdPlacementSetting;
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

function fakeAdJpeg(string $name = 'banner.jpg'): UploadedFile
{
    $content = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8VFRUVFRUVFRUVFRUVFRUVFRUWFhUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGxAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFxEBAQEBAAAAAAAAAAAAAAAAAQACIf/aAAwDAQACEAMQAAAB6gD/xAAZEAEBAAMBAAAAAAAAAAAAAAABAhIhMUH/2gAIAQEAAT8ApVdRX//EABYRAQEBAAAAAAAAAAAAAAAAAAABEf/aAAgBAgEBPwB//8QAFhEBAQEAAAAAAAAAAAAAAAAAAQAR/9oACAEDAQE/AYf/2Q==', true);

    if ($content === false) {
        throw new RuntimeException('The advertisement test JPEG could not be decoded.');
    }

    return UploadedFile::fake()->createWithContent($name, $content);
}

function fakeAdGif(string $name = 'banner.gif'): UploadedFile
{
    $content = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);

    if ($content === false) {
        throw new RuntimeException('The advertisement test GIF could not be decoded.');
    }

    return UploadedFile::fake()->createWithContent($name, $content);
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
        'fraud_status' => $status === 'capture' ? 'accept' : null,
        'transaction_id' => 'midtrans-tx-'.$campaign->id,
        'payment_type' => 'gopay',
    ];
}

test('verified vendors can start a Midtrans checkout for an admin-configured package', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.client_key' => 'test-client-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
        'services.midtrans.api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
    Storage::fake('local');
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
        ->assertRedirect(route('workspace', ['page' => 'advertisements']));
    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee('data-page-panel="advertisements"', false)
        ->assertSee('Banner samping')
        ->assertSee('1200 × 300 px')
        ->assertSee('300 × 900 px')
        ->assertSee('Rp200.000')
        ->assertSee('Judul iklan')
        ->assertSee('PNG, JPG/JPEG, GIF, WebP, BMP, atau AVIF');

    $checkoutResponse = $this->actingAs($vendor)
        ->postJson(route('vendor.advertisements.store'), [
            'ad_package_id' => $package->id,
            'ad_title' => 'Promo Kemasan Kopi',
            'destination_url' => 'https://vendor.example/promo',
            'asset' => fakeAdJpeg(),
        ])
        ->assertOk()
        ->assertJsonPath('token', 'snap-token-123');

    $campaign = AdCampaign::query()->firstOrFail();
    $checkoutResponse
        ->assertJsonPath('campaign_id', $campaign->id)
        ->assertJsonPath('status_url', route('vendor.advertisements.refresh', $campaign));

    expect($campaign->amount)->toBe(AdCampaign::SIDEBAR_PRICE)
        ->and($campaign->width)->toBe(AdCampaign::SIDEBAR_BANNER_WIDTH)
        ->and($campaign->height)->toBe(AdCampaign::SIDEBAR_BANNER_HEIGHT)
        ->and(substr($campaign->order_id, 0, 10))->toBe('NAVORA-AD-')
        ->and($campaign->snap_token)->toBe('snap-token-123')
        ->and($campaign->placement)->toBe('sidebar')
        ->and($campaign->ad_title)->toBe('Promo Kemasan Kopi')
        ->and($campaign->destination_url)->toBe('https://vendor.example/promo')
        ->and($campaign->asset_submitted_at)->not->toBeNull()
        ->and($campaign->status)->toBe(AdCampaign::STATUS_PENDING_PAYMENT);
    Storage::disk('local')->assertExists($campaign->asset_path);
    expect(Storage::disk('local')->mimeType($campaign->asset_path))->toBe('image/jpeg');

    Http::assertSent(fn ($request) => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        && $request['transaction_details']['gross_amount'] === AdCampaign::SIDEBAR_PRICE
        && $request['transaction_details']['order_id'] === $campaign->order_id);

    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($campaign->fresh()->payment_status)->toBe('paid')
        ->and($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED)
        ->and($campaign->fresh()->midtrans_transaction_id)->toBe('midtrans-tx-'.$campaign->id)
        ->and($campaign->fresh()->payment_type)->toBe('gopay');

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('1')
        ->assertSee('Pembayaran iklan');

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('Promo Kemasan Kopi')
        ->assertSee('Lunas · menunggu tinjauan admin')
        ->assertSee('Bukti pembayaran Midtrans')
        ->assertSee('Rp100.000')
        ->assertSee('href="https://vendor.example/promo"', false)
        ->assertSee('Pratinjau klik')
        ->assertDontSee('PAKET DAN TARIF')
        ->assertDontSee('Tambah paket')
        ->assertSee(route('admin.customize.campaigns.asset', $campaign), false);

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'approve',
            'reason' => 'Banner sesuai panduan.',
        ])
        ->assertRedirect();

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ACTIVE)
        ->and($campaign->fresh()->review_reason)->toBe('Banner sesuai panduan.');

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee('bi-envelope', false)
        ->assertSee('data-notifications-toggle', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Jelajahi demo')
        ->assertSee('href="'.route('workspace').'"', false)
        ->assertSee(route('advertisements.click', $campaign), false)
        ->assertSee(route('advertisements.asset', $campaign), false)
        ->assertSee('navora-ad-sidebar');

    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee('snap.pay(token', false)
        ->assertSee('app.sandbox.midtrans.com/snap/snap.js', false);
});

test('vendors cannot start an advertising checkout when the Midtrans server key is missing', function () {
    config(['services.midtrans.server_key' => null]);
    Http::preventStrayRequests();
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
        ->assertRedirect(route('workspace', ['page' => 'advertisements']));

    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee('Pembayaran iklan sementara belum tersedia.')
        ->assertSee('Pembayaran belum tersedia')
        ->assertSee('disabled', false);

    $this->actingAs($vendor)
        ->from(route('workspace', ['page' => 'advertisements']))
        ->post(route('vendor.advertisements.store'), [
            'ad_package_id' => $package->id,
            'ad_title' => 'Promo',
            'destination_url' => 'https://vendor.example/promo',
            'asset' => fakeAdImage(),
        ])
        ->assertRedirect(route('workspace', ['page' => 'advertisements']))
        ->assertSessionHasErrors([
            'payment' => 'Pembayaran Midtrans belum dikonfigurasi admin. Silakan hubungi admin Navora.',
        ]);

    expect(AdCampaign::query()->count())->toBe(0);
});

test('vendors cannot submit SVG or an insecure advertisement destination URL', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);
    Storage::fake('local');
    $vendor = createVerifiedAdVendor();
    $package = AdPackage::query()->where('is_active', true)->firstOrFail();

    $this->actingAs($vendor)
        ->from(route('workspace', ['page' => 'advertisements']))
        ->post(route('vendor.advertisements.store'), [
            'ad_package_id' => $package->id,
            'ad_title' => 'Promo',
            'destination_url' => 'http://vendor.example/promo',
            'asset' => UploadedFile::fake()->createWithContent('banner.svg', '<svg><script>alert(1)</script></svg>'),
        ])
        ->assertRedirect(route('workspace', ['page' => 'advertisements']))
        ->assertSessionHasErrors(['destination_url', 'asset']);

    expect(AdCampaign::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('advertisements'))->toBe([]);
});

test('signed Midtrans notifications record payment evidence and duplicate notices do not reset approval', function () {
    config(['services.midtrans.server_key' => 'test-server-key']);
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'asset_path' => 'advertisements/1/banner.png',
        'asset_submitted_at' => now(),
    ]);

    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED)
        ->and($campaign->fresh()->payment_status)->toBe('paid')
        ->and($campaign->fresh()->paid_at)->not->toBeNull()
        ->and($campaign->fresh()->midtrans_transaction_id)->toBe('midtrans-tx-'.$campaign->id);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign))
        ->assertOk();

    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ACTIVE);
});

test('vendor and admin can reconcile a missed Midtrans notification against the transaction API', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'amount' => AdCampaign::TOP_PRICE,
        'placement' => 'top',
        'asset_path' => 'advertisements/banner/top.png',
        'snap_token' => 'snap-token-for-paid-campaign',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/'.$campaign->order_id.'/status' => Http::response([
            'status_code' => '200',
            'order_id' => $campaign->order_id,
            'gross_amount' => '200000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-reconciled-transaction',
            'payment_type' => 'bank_transfer',
            'fraud_status' => 'accept',
        ]),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('Periksa ulang status pembayaran');

    $this->actingAs($admin)
        ->post(route('vendor.advertisements.refresh', $campaign))
        ->assertRedirect()
        ->assertSessionHas('status', 'Pembayaran berhasil diverifikasi Midtrans.');

    $this->actingAs($vendor)
        ->postJson(route('vendor.advertisements.refresh', $campaign))
        ->assertOk()
        ->assertJson([
            'payment_status' => 'paid',
            'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        ]);

    $campaign->refresh();
    expect($campaign->payment_status)->toBe('paid')
        ->and($campaign->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED)
        ->and($campaign->midtrans_transaction_id)->toBe('midtrans-reconciled-transaction')
        ->and($campaign->payment_type)->toBe('bank_transfer')
        ->and($campaign->paid_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('ID transaksi: midtrans-reconciled-transaction')
        ->assertSee('Lunas · menunggu tinjauan admin')
        ->assertSee(route('admin.customize.campaigns.asset', $campaign), false);

    $otherVendor = createVerifiedAdVendor();
    $this->actingAs($otherVendor)
        ->postJson(route('vendor.advertisements.refresh', $campaign))
        ->assertNotFound();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$campaign->order_id.'/status');
});

test('admin approval immediately publishes the paid asset to its placement', function () {
    Storage::fake('local');
    $vendor = createVerifiedAdVendor();
    $assetPath = fakeAdGif()->store('advertisements/1', 'local');
    $campaign = createAdCampaign($vendor, [
        'payment_status' => 'paid',
        'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        'placement' => 'top',
        'asset_path' => $assetPath,
        'paid_at' => now(),
        'midtrans_transaction_id' => 'midtrans-approved-transaction',
        'payment_type' => 'qris',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee($vendor->email)
        ->assertSee('Lunas · menunggu tinjauan admin')
        ->assertSee('ID transaksi: midtrans-approved-transaction');
    Storage::disk('local')->assertExists($assetPath);

    $this->actingAs($admin)
        ->get(route('admin.customize.campaigns.asset', $campaign))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaign::STATUS_ACTIVE)
        ->and($campaign->starts_at)->not->toBeNull()
        ->and($campaign->ends_at->isSameDay($campaign->starts_at->copy()->addDays(7)))->toBeTrue();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $campaign), false);
    $this->actingAs($vendor)
        ->get(route('vendor.advertisements.index'))
        ->assertRedirect(route('workspace', ['page' => 'advertisements']));
    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee(route('advertisements.asset', $campaign), false)
        ->assertSee('Iklan diterima dan ditayangkan')
        ->assertSee('Bukti pembayaran Midtrans');
    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee('bi-envelope', false)
        ->assertSee('data-page="notifications"', false)
        ->assertSee('data-ad-notifications-popup', false)
        ->assertSee('data-notification-item', false)
        ->assertSee('data-latest-ad-notice="'.$campaign->id.'"', false);
    $this->get(route('advertisements.asset', $campaign))
        ->assertOk()
        ->assertHeader('content-type', 'image/gif');
});

test('admin rejection records a reason, requests a Midtrans refund, and notifies the vendor', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    Storage::fake('local');
    $vendor = createVerifiedAdVendor();
    $assetPath = fakeAdImage()->store('advertisements/1', 'local');
    $campaign = createAdCampaign($vendor, [
        'placement' => 'sidebar',
        'amount' => AdCampaign::SIDEBAR_PRICE,
        'payment_status' => 'paid',
        'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        'asset_path' => $assetPath,
        'paid_at' => now(),
        'midtrans_transaction_id' => 'midtrans-rejected-transaction',
        'payment_type' => 'gopay',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $refundKey = 'NAVORA-AD-REFUND-'.$campaign->id;

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/midtrans-rejected-transaction/refund' => Http::response([
            'status_code' => '200',
            'transaction_id' => 'midtrans-rejected-transaction',
            'order_id' => $campaign->order_id,
            'transaction_status' => 'refund',
            'refund_key' => $refundKey,
        ]),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'reject',
        ])
        ->assertSessionHasErrors(['reason']);
    expect($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED);

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'reject',
            'reason' => 'Banner tidak sesuai panduan.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaign::STATUS_REJECTED)
        ->and($campaign->review_reason)->toBe('Banner tidak sesuai panduan.')
        ->and($campaign->refund_status)->toBe('requested')
        ->and($campaign->refund_key)->toBe($refundKey)
        ->and($campaign->refund_requested_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/midtrans-rejected-transaction/refund'
        && $request['refund_key'] === $refundKey
        && $request['amount'] === AdCampaign::SIDEBAR_PRICE
        && $request['reason'] === 'Iklan ditolak: Banner tidak sesuai panduan.');

    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee('Iklan ditolak admin')
        ->assertSee('Banner tidak sesuai panduan.')
        ->assertSee('Permintaan refund diterima Midtrans')
        ->assertSee('ID transaksi: midtrans-rejected-transaction');

    config(['services.midtrans.server_key' => 'test-server-key']);
    $this->postJson(route('midtrans.notifications'), midtransNotificationFor($campaign, 'refund'))
        ->assertOk();

    expect($campaign->fresh()->payment_status)->toBe('refunded')
        ->and($campaign->fresh()->refund_status)->toBe('refunded');

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('Pendapatan iklan')
        ->assertSee('Rp0');
});

test('a failed Midtrans refund can be retried with the same idempotency key', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
        'services.midtrans.api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'payment_status' => 'paid',
        'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        'asset_path' => 'advertisements/banner.png',
        'paid_at' => now(),
        'midtrans_transaction_id' => 'midtrans-retry-transaction',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $refundKey = 'NAVORA-AD-REFUND-'.$campaign->id;

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/midtrans-retry-transaction/refund' => Http::sequence()
            ->push(['status_code' => '500', 'status_message' => 'Temporary failure'], 500)
            ->push([
                'status_code' => '200',
                'transaction_id' => 'midtrans-retry-transaction',
                'order_id' => $campaign->order_id,
                'transaction_status' => 'refund',
                'refund_key' => $refundKey,
            ]),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'reject',
            'reason' => 'Konten tidak sesuai.',
        ])
        ->assertSessionHasErrors('refund');
    expect($campaign->fresh()->refund_status)->toBe('failed');

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.refund.retry', $campaign))
        ->assertSessionHas('status');

    expect($campaign->fresh()->refund_status)->toBe('requested');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['refund_key'] === $refundKey);
});

test('admin rejection cancels an authorized but unsettled Midtrans capture', function () {
    config([
        'services.midtrans.server_key' => 'test-server-key',
        'services.midtrans.base_url' => 'https://app.sandbox.midtrans.com',
        'services.midtrans.api_base_url' => 'https://api.sandbox.midtrans.com',
    ]);
    Http::preventStrayRequests();
    $vendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'asset_path' => 'advertisements/banner.png',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $transactionId = 'midtrans-authorized-capture';

    $this->postJson(route('midtrans.notifications'), [
        ...midtransNotificationFor($campaign, 'capture'),
        'payment_type' => 'credit_card',
        'transaction_id' => $transactionId,
    ])->assertOk();

    expect($campaign->fresh()->midtrans_transaction_status)->toBe('capture');

    Http::fake([
        'https://api.sandbox.midtrans.com/v2/midtrans-authorized-capture/cancel' => Http::response([
            'status_code' => '200',
            'transaction_id' => $transactionId,
            'order_id' => $campaign->order_id,
            'transaction_status' => 'cancel',
        ]),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaign), [
            'decision' => 'reject',
            'reason' => 'Iklan tidak sesuai.',
        ])
        ->assertSessionHas('status');

    expect($campaign->fresh()->payment_status)->toBe('cancelled')
        ->and($campaign->fresh()->refund_status)->toBe('cancelled');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.midtrans.com/v2/midtrans-authorized-capture/cancel');
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

test('UMKM receives a vendor-only message when clicking an advertisement', function () {
    $vendor = createVerifiedAdVendor();
    $buyer = User::factory()->create(['role' => 'umkm']);
    $campaign = createAdCampaign($vendor, [
        'destination_url' => 'https://vendor.example/promo',
        'status' => AdCampaign::STATUS_ACTIVE,
        'payment_status' => 'paid',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
    ]);

    $this->actingAs($buyer)
        ->get(route('advertisements.click', $campaign))
        ->assertOk()
        ->assertSee('Iklan hanya khusus vendor untuk saat ini');

    $this->actingAs($vendor)
        ->get(route('advertisements.click', $campaign))
        ->assertRedirect('https://vendor.example/promo');
});

test('admin can configure ad slot limits and cannot approve beyond capacity', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $vendor = createVerifiedAdVendor();
    AdPlacementSetting::query()->where('placement', 'top')->update(['max_active_ads' => 1]);
    $activeCampaign = createAdCampaign($vendor, [
        'placement' => 'global',
        'status' => AdCampaign::STATUS_ACTIVE,
        'payment_status' => 'paid',
        'asset_path' => 'advertisements/banner/top.png',
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
    ]);
    $campaignToReview = createAdCampaign($vendor, [
        'placement' => 'top',
        'status' => AdCampaign::STATUS_ASSET_SUBMITTED,
        'payment_status' => 'paid',
        'asset_path' => 'advertisements/banner/pending.png',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('Batas iklan aktif')
        ->assertSee('Banner atas (aktif: 1)');

    $this->actingAs($admin)
        ->post(route('admin.customize.campaigns.review', $campaignToReview), [
            'decision' => 'approve',
        ])
        ->assertSessionHasErrors('capacity');

    expect($campaignToReview->fresh()->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED)
        ->and($activeCampaign->fresh()->status)->toBe(AdCampaign::STATUS_ACTIVE);

    $this->actingAs($admin)
        ->post(route('admin.customize.slot-settings.update'), [
            'top' => 3,
            'sidebar' => 4,
        ])
        ->assertSessionHas('status');

    expect(AdPlacementSetting::query()->where('placement', 'top')->value('max_active_ads'))->toBe(3)
        ->and(AdPlacementSetting::query()->where('placement', 'sidebar')->value('max_active_ads'))->toBe(4);
});

test('homepage and workspace show clickable ad-placement calls to action when no ads are active', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('login').'"', false)
        ->assertSee('Masuk untuk mencari')
        ->assertSee('href="'.route('login').'">Masuk untuk mencari vendor', false)
        ->assertDontSee('action="'.route('workspace').'"', false)
        ->assertSee('Ingin pasang iklan?')
        ->assertSee('Klik di sini')
        ->assertSee(route('workspace', ['page' => 'advertisements']), false)
        ->assertSee('navora-ad-sidebar-promo');
});

test('homepage search gives UMKM and vendors role-specific destinations', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    BusinessProfile::factory()->for($buyer)->create(['completed_at' => now()]);
    $vendor = createVerifiedAdVendor();

    $this->actingAs($buyer)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('workspace').'"', false)
        ->assertSee('Cari vendor');

    $this->actingAs($vendor)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('workspace', ['page' => 'listings']).'"', false)
        ->assertSee('Kelola katalog saya');
});

test('vendors can edit paid advertisements and edited live campaigns return to admin review', function () {
    Storage::fake('local');
    $vendor = createVerifiedAdVendor();
    $otherVendor = createVerifiedAdVendor();
    $campaign = createAdCampaign($vendor, [
        'ad_title' => 'Judul awal',
        'destination_url' => 'https://vendor.example/awal',
        'asset_path' => 'advertisements/'.$vendor->id.'/banner.png',
        'status' => AdCampaign::STATUS_ACTIVE,
        'payment_status' => 'paid',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
        'reviewed_at' => now()->subDay(),
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($vendor)
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertOk()
        ->assertSee('Edit iklan')
        ->assertSee('data-page-panel="advertisements"', false)
        ->assertSee('Perubahan akan menghentikan tayang sementara');

    $this->actingAs($vendor)
        ->post(route('vendor.advertisements.update', $campaign), [
            'ad_title' => 'Judul baru',
            'destination_url' => 'https://vendor.example/baru',
            'asset' => fakeAdImage('updated.png'),
        ])
        ->assertSessionHas('status', 'Iklan berhasil diperbarui dan dikirim kembali untuk ditinjau admin.');

    expect($campaign->fresh()->ad_title)->toBe('Judul baru')
        ->and($campaign->fresh()->destination_url)->toBe('https://vendor.example/baru')
        ->and($campaign->fresh()->status)->toBe(AdCampaign::STATUS_ASSET_SUBMITTED)
        ->and($campaign->fresh()->starts_at)->toBeNull()
        ->and($campaign->fresh()->ends_at)->toBeNull()
        ->and($campaign->fresh()->reviewed_at)->toBeNull();
    Storage::disk('local')->assertExists($campaign->fresh()->asset_path);

    $this->actingAs($otherVendor)
        ->post(route('vendor.advertisements.update', $campaign), [
            'ad_title' => 'Tidak berhak',
            'destination_url' => 'https://vendor.example/tidak-berhak',
        ])
        ->assertNotFound();
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
        ->get(route('workspace', ['page' => 'advertisements']))
        ->assertRedirect(route('vendor.verification.index'));

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

test('admin advertisement page no longer exposes package and tariff controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.customize.index'))
        ->assertOk()
        ->assertSee('Iklan dan pendapatan Navora')
        ->assertDontSee('PAKET DAN TARIF')
        ->assertDontSee('Slot iklan')
        ->assertDontSee('Tambah paket')
        ->assertDontSee('Advertisement)');

    $this->actingAs($admin)
        ->post('/admin/customize/packages')
        ->assertNotFound();
});
