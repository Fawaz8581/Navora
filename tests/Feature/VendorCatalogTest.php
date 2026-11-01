<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('filters verified vendors by catalogue details and approximate distance', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $nearVendor = User::factory()->create(['role' => 'vendor']);
    $farVendor = User::factory()->create(['role' => 'vendor']);
    $unverifiedVendor = User::factory()->create(['role' => 'vendor']);

    BusinessProfile::factory()->for($nearVendor)->create([
        'business_name' => 'Pouch Bandung',
        'verification_status' => 'approved',
        'completed_at' => now(),
        'product_categories' => 'Pouch kemasan',
        'materials' => 'Kraft laminasi',
        'minimum_order_quantity' => 500,
        'location' => 'Bandung',
        'indicative_price_per_unit' => 1250,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'work_sample_paths' => ['private/sample.png'],
        'certificate_paths' => ['private/certificate.pdf'],
    ]);

    BusinessProfile::factory()->for($farVendor)->create([
        'verification_status' => 'approved',
        'completed_at' => now(),
        'product_categories' => 'Pouch kemasan',
        'materials' => 'Kraft',
        'minimum_order_quantity' => 500,
        'location' => 'Bandung',
        'indicative_price_per_unit' => 1250,
        'latitude' => -7.25,
        'longitude' => 112.75,
        'work_sample_paths' => ['private/far-sample.png'],
        'certificate_paths' => ['private/far-certificate.pdf'],
    ]);

    BusinessProfile::factory()->for($unverifiedVendor)->create([
        'verification_status' => 'pending',
        'completed_at' => now(),
        'product_categories' => 'Pouch kemasan',
        'materials' => 'Kraft',
        'minimum_order_quantity' => 500,
        'location' => 'Bandung',
        'indicative_price_per_unit' => 1250,
        'latitude' => -6.2,
        'longitude' => 106.8,
        'work_sample_paths' => ['private/sample.png'],
        'certificate_paths' => ['private/certificate.pdf'],
    ]);

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', [
            'search' => 'Pouch',
            'category' => 'pouch',
            'max_moq' => 700,
            'material' => 'kraft',
            'location' => 'Bandung',
            'has_sample' => 1,
            'has_certificate' => 1,
            'max_price' => 2000,
            'distance_km' => 30,
            'origin_lat' => -6.2,
            'origin_lng' => 106.8,
            'sort' => 'distance',
        ]))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('vendors.0.id', $nearVendor->id)
        ->assertJsonPath('vendors.0.images.0', route('vendors.catalog.image', [$nearVendor->businessProfile, 0]))
        ->assertJsonPath('vendors.0.distance_km', 0)
        ->assertJsonPath('vendors.0.price_per_unit', '1250.00')
        ->assertJsonMissingPath('vendors.0.work_sample_paths')
        ->assertJsonMissingPath('vendors.0.certificate_paths');
});

it('excludes vendors beyond the selected radius before rounding the displayed distance', function () {
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);

    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
        'completed_at' => now(),
        'latitude' => 0,
        'longitude' => 0.27,
    ]);

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', [
            'distance_km' => 30,
            'origin_lat' => 0,
            'origin_lng' => 0,
        ]))
        ->assertOk()
        ->assertJsonPath('count', 0);
});

it('serves approved vendor product photos only to UMKM catalogue users', function () {
    Storage::fake('local');
    $buyer = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    $profile = BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
        'completed_at' => now(),
    ]);
    $samplePath = 'vendor-verification/'.$profile->id.'/work_samples/sample.png';
    $profile->forceFill(['work_sample_paths' => [$samplePath]])->save();
    Storage::disk('local')->put(
        $samplePath,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true),
    );

    $this->actingAs($buyer)
        ->get(route('vendors.catalog.image', [$profile, 0]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('cache-control', 'max-age=300, private');

    $this->actingAs($vendor)
        ->get(route('vendors.catalog.image', [$profile, 0]))
        ->assertForbidden();

    $profile->forceFill(['verification_status' => 'pending'])->save();
    $this->actingAs($buyer)
        ->get(route('vendors.catalog.image', [$profile, 0]))
        ->assertNotFound();
});

it('returns forbidden for non-UMKM accounts and rejects a radius without an origin', function () {
    $vendor = User::factory()->create(['role' => 'vendor']);
    $buyer = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($vendor)
        ->getJson(route('vendors.catalog'))
        ->assertForbidden();

    $this->actingAs($buyer)
        ->getJson(route('vendors.catalog', ['distance_km' => 25]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['distance_km']);
});
