<?php

use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\VendorListing;
use App\Services\VideoDurationInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createCatalogVendor(bool $verified): User
{
    $vendor = User::factory()->create(['role' => 'vendor']);

    BusinessProfile::factory()->for($vendor)->create([
        'completed_at' => now(),
        'verification_status' => $verified ? 'approved' : 'pending',
        'business_name' => 'Vendor Katalog',
        'product_categories' => 'Kemasan',
        'materials' => 'Kraft',
    ]);

    return $vendor;
}

function vendorListingPayload(string $clientReference = 'listing-reference-1'): array
{
    return [
        'client_reference' => $clientReference,
        'name' => 'Standing pouch custom',
        'category' => 'packaging',
        'material' => 'Kraft laminasi',
        'minimum_order_quantity' => 500,
        'price_per_unit' => 1500,
        'pricing_type' => VendorListing::PRICING_FINAL,
        'description' => 'Pouch custom untuk produk kopi.',
    ];
}

it('shows a vendor listing to UMKM only after an admin approves it', function () {
    $vendor = createCatalogVendor(true);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_PENDING);
    $listingId = $response->json('listing.id');

    expect($vendor->vendorListings()->sole()->status)->toBe(VendorListing::STATUS_PENDING);

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', ['search' => 'Standing pouch custom']))
        ->assertOk()
        ->assertJsonPath('count', 0);

    $this->actingAs($admin)
        ->get(route('admin.vendor-listings.index'))
        ->assertOk()
        ->assertSee('Standing pouch custom')
        ->assertSee('Setujui dan aktifkan');

    $this->actingAs($admin)
        ->post(route('admin.vendor-listings.approve', $listingId))
        ->assertRedirect(route('admin.vendor-listings.index'))
        ->assertSessionHas('status');

    $this->actingAs($admin)
        ->post(route('admin.vendor-listings.approve', $listingId))
        ->assertConflict();

    $this->assertDatabaseHas('vendor_listings', [
        'id' => $listingId,
        'status' => VendorListing::STATUS_ACTIVE,
        'approved_by' => $admin->id,
    ]);
    expect($vendor->vendorListings()->sole()->approved_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('admin.vendor-listings.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('Aktif')
        ->assertSee($admin->name)
        ->assertDontSee('Setujui dan aktifkan');

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', ['search' => 'Standing pouch custom']))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('vendors.0.listings.0.name', 'Standing pouch custom')
        ->assertJsonPath('vendors.0.listings.0.price_per_unit', 1500)
        ->assertJsonPath('vendors.0.listings.0.pricing_type', VendorListing::PRICING_FINAL);
});

it('requires profile verification before a vendor can submit a listing for review', function () {
    $vendor = createCatalogVendor(false);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertForbidden();

    expect($vendor->vendorListings()->count())->toBe(0);
});

it('preserves approval and active status when a vendor edits a published listing', function () {
    $vendor = createCatalogVendor(true);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_PENDING);

    $listing = $vendor->vendorListings()->sole();

    $this->actingAs($admin)
        ->post(route('admin.vendor-listings.approve', $listing))
        ->assertRedirect();

    $listing = $vendor->vendorListings()->sole();
    $approvedAt = $listing->fresh()->approved_at->toDateTimeString();

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'name' => 'Standing pouch kopi revisi',
            'price_per_unit' => 1700,
        ])
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_ACTIVE)
        ->assertJsonPath('listing.name', 'Standing pouch kopi revisi')
        ->assertJsonPath('listing.price_per_unit', 1700);

    $this->assertDatabaseHas('vendor_listings', [
        'id' => $listing->id,
        'status' => VendorListing::STATUS_ACTIVE,
        'approved_by' => $admin->id,
        'name' => 'Standing pouch kopi revisi',
        'price_per_unit' => 1700,
    ]);

    expect($listing->fresh()->approved_at->toDateTimeString())->toBe($approvedAt);

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', ['search' => 'Standing pouch kopi revisi']))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('vendors.0.listings.0.name', 'Standing pouch kopi revisi')
        ->assertJsonPath('vendors.0.listings.0.price_per_unit', 1700);
});

it('prevents non-vendors from managing listings and non-admins from approving them', function () {
    $owner = createCatalogVendor(true);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $listing = $owner->vendorListings()->create([
        ...vendorListingPayload(),
        'status' => VendorListing::STATUS_PENDING,
    ]);

    $this->actingAs($buyer)
        ->getJson(route('vendor.listings.index'))
        ->assertForbidden();

    $this->actingAs($buyer)
        ->post(route('admin.vendor-listings.approve', $listing))
        ->assertForbidden();

    expect($listing->fresh()->status)->toBe(VendorListing::STATUS_PENDING);
});

it('rejects invalid listing data without creating a catalog entry', function () {
    $vendor = createCatalogVendor(true);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'category' => 'invalid',
            'minimum_order_quantity' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category', 'minimum_order_quantity']);

    expect($vendor->vendorListings()->count())->toBe(0);
});

it('allows vendors to set negotiable prices or require a final unit price', function () {
    $vendor = createCatalogVendor(true);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'pricing_type' => VendorListing::PRICING_NEGOTIABLE,
            'price_per_unit' => 1800,
        ])
        ->assertCreated()
        ->assertJsonPath('listing.pricing_type', VendorListing::PRICING_NEGOTIABLE)
        ->assertJsonPath('listing.price_per_unit', 1800);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload('listing-without-price'),
            'pricing_type' => VendorListing::PRICING_NEGOTIABLE,
            'price_per_unit' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('listing.pricing_type', VendorListing::PRICING_NEGOTIABLE)
        ->assertJsonPath('listing.price_per_unit', null);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload('listing-final-without-price'),
            'pricing_type' => VendorListing::PRICING_FINAL,
            'price_per_unit' => null,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price_per_unit');

    expect($vendor->vendorListings()->count())->toBe(2);
});

it('lets vendors replace and remove listing attachments with a maximum of four', function () {
    Storage::fake('local');
    $vendor = createCatalogVendor(true);
    $admin = User::factory()->create(['role' => 'admin']);
    $listing = $vendor->vendorListings()->create([
        ...vendorListingPayload(),
        'status' => VendorListing::STATUS_ACTIVE,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);
    $uploadImages = fn (string $prefix): array => collect(range(1, 4))
        ->map(fn (int $number) => UploadedFile::fake()->createWithContent("{$prefix}-{$number}.png", $png))
        ->all();

    $this->actingAs($vendor)
        ->post(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'media' => $uploadImages('initial'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(4, 'listing.media');

    $listing->load('media');
    $initialMedia = $listing->media->all();
    $this->assertDatabaseHas('vendor_listings', [
        'id' => $listing->id,
        'status' => VendorListing::STATUS_ACTIVE,
        'approved_by' => $admin->id,
    ]);

    $replacementResponse = $this->actingAs($vendor)
        ->post(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'remove_media_ids' => [$initialMedia[0]->id, $initialMedia[1]->id],
            'media' => [
                UploadedFile::fake()->createWithContent('replacement-1.png', $png),
                UploadedFile::fake()->createWithContent('replacement-2.png', $png),
            ],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(4, 'listing.media');

    Storage::disk('local')->assertMissing($initialMedia[0]->path);
    Storage::disk('local')->assertMissing($initialMedia[1]->path);
    $replacementMediaPaths = $listing->media()->pluck('path')->all();
    $currentMediaIds = collect($replacementResponse->json('listing.media'))
        ->pluck('id')
        ->all();

    $this->actingAs($vendor)
        ->post(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'media' => [UploadedFile::fake()->createWithContent('fifth.png', $png)],
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media');

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'remove_media_ids' => $currentMediaIds,
        ])
        ->assertCreated()
        ->assertJsonCount(0, 'listing.media');

    foreach (array_slice($initialMedia, 2) as $media) {
        Storage::disk('local')->assertMissing($media->path);
    }
    foreach ($replacementMediaPaths as $path) {
        Storage::disk('local')->assertMissing($path);
    }

    expect($listing->media()->count())->toBe(0);
    $this->assertDatabaseHas('vendor_listings', [
        'id' => $listing->id,
        'status' => VendorListing::STATUS_ACTIVE,
        'approved_by' => $admin->id,
    ]);

    $anotherVendor = createCatalogVendor(true);
    $otherListing = $anotherVendor->vendorListings()->create([
        ...vendorListingPayload('another-vendor-listing'),
        'status' => VendorListing::STATUS_ACTIVE,
    ]);
    $otherPath = UploadedFile::fake()->createWithContent('private.png', $png)->store('vendor-catalog/private', 'local');
    $otherMedia = $otherListing->media()->create([
        'path' => $otherPath,
        'mime_type' => 'image/png',
        'media_type' => 'image',
        'position' => 0,
    ]);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'remove_media_ids' => [$otherMedia->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('remove_media_ids');

    $this->assertDatabaseHas('vendor_listing_media', ['id' => $otherMedia->id]);
    Storage::disk('local')->assertExists($otherPath);
});

it('stores multiple product images and GIFs and shows them in the approved UMKM catalogue', function () {
    Storage::fake('local');
    $vendor = createCatalogVendor(true);
    $buyer = User::factory()->create(['role' => 'umkm']);
    $admin = User::factory()->create(['role' => 'admin']);
    $differentVendor = createCatalogVendor(true);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);
    $gif = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', true);
    $video = UploadedFile::fake()->create('promo.mp4', 1, 'video/mp4');
    $inspector = Mockery::mock(VideoDurationInspector::class);
    $inspector->shouldReceive('duration')->once()->andReturn(5.0);
    $this->app->instance(VideoDurationInspector::class, $inspector);

    $submission = $this->actingAs($vendor)
        ->post(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'media' => [
                UploadedFile::fake()->createWithContent('front.png', $png),
                UploadedFile::fake()->createWithContent('detail.gif', $gif),
                $video,
            ],
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonCount(3, 'listing.media');

    $listing = $vendor->vendorListings()->with('media')->sole();
    expect($listing->media)->toHaveCount(3)
        ->and($listing->media[0]->media_type)->toBe('image')
        ->and($listing->media[1]->mime_type)->toBe('image/gif')
        ->and($listing->media[2]->media_type)->toBe('video');
    Storage::disk('local')->assertExists($listing->media[0]->path);
    Storage::disk('local')->assertExists($listing->media[1]->path);
    Storage::disk('local')->assertExists($listing->media[2]->path);

    $imageUrl = $submission->json('listing.media.0.url');
    $this->actingAs($buyer)->get($imageUrl)->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.vendor-listings.index'))
        ->assertOk()
        ->assertSee($imageUrl, false);

    $this->get($imageUrl)->assertOk()->assertHeader('content-type', 'image/png');

    $this->actingAs($admin)
        ->post(route('admin.vendor-listings.approve', $listing))
        ->assertRedirect();

    $catalog = $this->actingAs($buyer)
        ->getJson(route('vendors.catalog'))
        ->assertOk()
        ->assertJsonPath('vendors.0.listings.0.name', 'Standing pouch custom')
        ->assertJsonCount(3, 'vendors.0.listings.0.media');

    $mediaUrls = collect($catalog->json('vendors.0.listings.0.media'))->pluck('url');
    $this->get($mediaUrls[0])->assertOk()->assertHeader('content-type', 'image/png');
    $this->get($mediaUrls[1])->assertOk()->assertHeader('content-type', 'image/gif');

    $this->actingAs($vendor)
        ->get($mediaUrls[0])
        ->assertOk();

    $this->actingAs($differentVendor)
        ->get($mediaUrls[0])
        ->assertNotFound();
});

it('enforces the five-second video limit on the server', function () {
    $vendor = createCatalogVendor(true);
    $video = UploadedFile::fake()->create('promo.mp4', 1, 'video/mp4');
    $inspector = Mockery::mock(VideoDurationInspector::class);
    $inspector->shouldReceive('duration')->once()->andReturn(5.01);
    $this->app->instance(VideoDurationInspector::class, $inspector);

    $this->actingAs($vendor)
        ->post(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'media' => [$video],
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('media');

    expect($vendor->vendorListings()->count())->toBe(0);
});
