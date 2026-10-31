<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->assertJsonPath('vendors.0.distance_km', 0)
        ->assertJsonPath('vendors.0.price_per_unit', '1250.00')
        ->assertJsonMissingPath('vendors.0.work_sample_paths')
        ->assertJsonMissingPath('vendors.0.certificate_paths');
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
