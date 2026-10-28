<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new accounts can only choose UMKM or vendor and start onboarding', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Pemilik brand / UMKM')
        ->assertSee('Vendor kemasan atau maklon')
        ->assertDontSee('Mitra pendukung UMKM');

    $this->post(route('register.store'), [
        'name' => 'Navora Mitra',
        'email' => 'partner@example.com',
        'role' => 'partner',
        'password' => 'secure-password-123',
        'password_confirmation' => 'secure-password-123',
    ])->assertSessionHasErrors('role');

    expect(User::count())->toBe(0);

    $this->post(route('register.store'), [
        'name' => 'Vendor Navora',
        'email' => 'vendor@example.com',
        'role' => 'vendor',
        'password' => 'secure-password-123',
        'password_confirmation' => 'secure-password-123',
    ])->assertRedirect(route('onboarding.index'));

    $user = User::where('email', 'vendor@example.com')->firstOrFail();

    expect($user->businessProfile)->not->toBeNull()
        ->and($user->businessProfile->onboarding_step)->toBe(1)
        ->and($user->businessProfile->completed_at)->toBeNull();
});

test('business onboarding is authenticated and cannot skip a required step', function () {
    $this->get(route('onboarding.index'))->assertRedirect(route('login'));

    $user = User::factory()->create(['role' => 'umkm']);
    $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->get(route('onboarding.step', ['step' => 3]))
        ->assertRedirect(route('onboarding.step', ['step' => 1]));
});

test('onboarding falls back to the first step when the saved step is invalid', function () {
    $user = User::factory()->create(['role' => 'umkm']);
    $user->businessProfile()->create(['onboarding_step' => 0]);

    $this->actingAs($user)
        ->get(route('onboarding.index'))
        ->assertRedirect(route('onboarding.step', ['step' => 1]));
});

test('UMKM can save their business and packaging needs and resume onboarding', function () {
    $user = User::factory()->create(['role' => 'umkm']);
    $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kopi Nusantara',
            'phone' => '08123456789',
            'category' => 'Makanan dan minuman',
            'location' => 'Bandung',
            'description' => 'Produsen kopi lokal.',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 2]));

    $this->get(route('onboarding.index'))
        ->assertRedirect(route('onboarding.step', ['step' => 2]));

    $this->post(route('onboarding.update', ['step' => 2]), [
        'product_type' => 'Kopi bubuk',
        'packaging_type' => 'Standing pouch',
        'preferred_material' => 'Kraft',
        'monthly_quantity' => '1500',
    ])->assertRedirect(route('onboarding.step', ['step' => 3]));

    $this->post(route('onboarding.update', ['step' => 3]), [
        'confirm_profile' => '1',
    ])->assertRedirect(route('business-profile.show'));

    $profile = $user->fresh()->businessProfile;

    expect($profile->business_name)->toBe('Kopi Nusantara')
        ->and($profile->product_type)->toBe('Kopi bubuk')
        ->and($profile->monthly_quantity)->toBe(1500)
        ->and($profile->completed_at)->not->toBeNull();

    $this->get(route('workspace'))
        ->assertOk()
        ->assertSee('Profil usahamu')
        ->assertSee('Kopi Nusantara');

    $this->get(route('business-profile.show'))
        ->assertOk()
        ->assertSee('Profil usahamu');

    $this->get(route('onboarding.step', ['step' => 1]))
        ->assertOk()
        ->assertSee('value="Kopi Nusantara"', false);
});

test('vendors must provide MOQ and can save vendor details', function () {
    $user = User::factory()->create(['role' => 'vendor']);
    $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kemasan Prima',
            'category' => 'Manufaktur kemasan',
            'location' => 'Jakarta',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 2]));

    $this->from(route('onboarding.step', ['step' => 2]))
        ->post(route('onboarding.update', ['step' => 2]), [
            'product_categories' => 'Pouch dan botol',
            'minimum_order_quantity' => '0',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 2]))
        ->assertSessionHasErrors('minimum_order_quantity');

    $this->post(route('onboarding.update', ['step' => 2]), [
        'product_categories' => 'Pouch dan botol',
        'materials' => 'Kraft, PET',
        'minimum_order_quantity' => '500',
        'monthly_capacity' => '12000',
        'production_lead_time_days' => '14',
    ])->assertRedirect(route('onboarding.step', ['step' => 3]));

    $profile = $user->fresh()->businessProfile;

    expect($profile->product_categories)->toBe('Pouch dan botol')
        ->and($profile->minimum_order_quantity)->toBe(500)
        ->and($profile->monthly_capacity)->toBe(12000)
        ->and($profile->production_lead_time_days)->toBe(14);
});

test('existing partner accounts remain able to log in and access the workspace', function () {
    $partner = User::factory()->create([
        'role' => 'partner',
        'email' => 'partner@example.com',
        'password' => bcrypt('secure-password-123'),
    ]);

    $this->post(route('login.store'), [
        'email' => $partner->email,
        'password' => 'secure-password-123',
    ])->assertRedirect(route('workspace'));

    $this->get(route('workspace'))->assertOk();

    expect(BusinessProfile::count())->toBe(0);
});
