<?php

use App\Models\BusinessProfile;
use App\Models\User;
use App\Models\VendorListing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recommendation endpoint validates required fields', function () {
    $response = $this->postJson(route('packaging-assistant.recommend'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['product', 'volume']);
});

test('recommendation returns comprehensive packaging advice for coffee', function () {
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'business_name' => 'Pabrik Kemasan Kopi Nusantara',
        'location' => 'Bandung',
        'verification_status' => 'approved',
    ]);
    VendorListing::create([
        'vendor_user_id' => $vendor->id,
        'client_reference' => 'ref-test-coffee-1',
        'name' => 'Standing Pouch Kopi Valve Zipper',
        'category' => 'packaging',
        'description' => 'Kemasan foil kopi tebal dengan one-way valve',
        'material' => 'Alufoil 120 mikron',
        'minimum_order_quantity' => 500,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('packaging-assistant.recommend'), [
        'product' => 'Kopi Arabika Gayo Bubuk',
        'size' => '250 gram',
        'volume' => 'medium',
        'priority' => 'shelf_life',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'product',
            'category_detected',
            'characteristics',
            'packaging_types',
            'material_specification',
            'cost_estimation' => [
                'estimated_price_range',
                'recommended_printing',
            ],
            'volume_strategy',
            'regulatory_compliance' => [
                'label_checklist',
                'storage_tips',
            ],
            'vendor_recommendations',
            'rfq_prefill',
        ]);

    expect($response->json('category_detected'))->toContain('Kopi')
        ->and($response->json('packaging_types'))->not->toBeEmpty()
        ->and($response->json('regulatory_compliance.label_checklist'))->not->toBeEmpty();
});

test('recommendation analyzes frozen food and liquid categories', function () {
    $frozenResponse = $this->postJson(route('packaging-assistant.recommend'), [
        'product' => 'Dimsum Ayam Beku',
        'size' => '10 pcs / 300 gram',
        'volume' => 'small',
        'priority' => 'quality',
    ]);

    $frozenResponse->assertOk();
    expect($frozenResponse->json('category_detected'))->toContain('Beku');

    $liquidResponse = $this->postJson(route('packaging-assistant.recommend'), [
        'product' => 'Sambal Matah Bali',
        'size' => '150 ml',
        'volume' => 'large',
        'priority' => 'budget',
    ]);

    $liquidResponse->assertOk();
    expect($liquidResponse->json('category_detected'))->toContain('Cairan');
});
