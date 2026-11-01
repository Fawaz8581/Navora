<?php

use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\VendorListing;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertJsonPath('vendors.0.listings.0.name', 'Standing pouch custom');
});

it('requires profile verification before a vendor can submit a listing for review', function () {
    $vendor = createCatalogVendor(false);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertForbidden();

    expect($vendor->vendorListings()->count())->toBe(0);
});

it('preserves approval on an identical retry and sends edited listings back for review', function () {
    $vendor = createCatalogVendor(true);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_PENDING);

    $listing = $vendor->vendorListings()->sole();

    $this->actingAs($admin)
        ->post(route('admin.vendor-listings.approve', $listing))
        ->assertRedirect();

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), vendorListingPayload())
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_ACTIVE);

    $this->actingAs($vendor)
        ->postJson(route('vendor.listings.store'), [
            ...vendorListingPayload(),
            'name' => 'Standing pouch kopi revisi',
        ])
        ->assertCreated()
        ->assertJsonPath('listing.status', VendorListing::STATUS_PENDING)
        ->assertJsonPath('listing.name', 'Standing pouch kopi revisi');

    expect($listing->fresh()->approved_by)->toBeNull();
    expect($vendor->vendorListings()->count())->toBe(1);
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
