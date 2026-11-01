<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('registration does not create an account until all required profile details are confirmed', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Profil usaha')
        ->assertSee('Kebutuhan bisnis')
        ->assertSee('Tinjau kembali informasi sebelum membuat akun.')
        ->assertDontSee('Hapus akun Navora');

    $this->post(route('register.store'), [
        'name' => 'Navora UMKM',
        'email' => 'umkm@example.com',
        'role' => 'umkm',
        'password' => 'secure-password-123',
        'password_confirmation' => 'secure-password-123',
    ])->assertSessionHasErrors(['business_name', 'category', 'location', 'product_type', 'confirm_profile']);

    expect(User::count())->toBe(0)
        ->and(BusinessProfile::count())->toBe(0);

    $this->assertGuest();
});

test('business onboarding is authenticated and presented on one page', function () {
    $this->get(route('onboarding.index'))->assertRedirect(route('login'));

    $user = User::factory()->create(['role' => 'umkm']);
    $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->get(route('onboarding.index'))
        ->assertOk()
        ->assertSee('Profil usaha')
        ->assertSee('Kebutuhan bisnis')
        ->assertSee('Tinjau profil')
        ->assertDontSee('Hapus akun Navora');

    $this->get(route('onboarding.step', ['step' => 3]))
        ->assertRedirect(route('onboarding.index'));
});

test('UMKM can complete their business and packaging needs in one submission', function () {
    $user = User::factory()->create(['role' => 'umkm']);
    $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kopi Nusantara',
            'phone' => '08123456789',
            'category' => 'Makanan dan minuman',
            'location' => 'Bandung',
            'description' => 'Produsen kopi lokal.',
            'product_type' => 'Kopi bubuk',
            'packaging_type' => 'Standing pouch',
            'preferred_material' => 'Kraft',
            'monthly_quantity' => '1500',
            'confirm_profile' => '1',
        ])
        ->assertRedirect(route('business-profile.show'));

    $profile = $user->fresh()->businessProfile;

    expect($profile->business_name)->toBe('Kopi Nusantara')
        ->and($profile->product_type)->toBe('Kopi bubuk')
        ->and($profile->monthly_quantity)->toBe(1500)
        ->and($profile->onboarding_step)->toBe(3)
        ->and($profile->completed_at)->not->toBeNull();

    $this->get(route('workspace'))
        ->assertOk()
        ->assertSee('Profil usahamu')
        ->assertSee('Kopi Nusantara');

    $this->get(route('business-profile.show'))
        ->assertOk()
        ->assertSee('Profil usahamu')
        ->assertSee('Hapus akun Navora');

    $this->get(route('onboarding.step', ['step' => 1]))
        ->assertOk()
        ->assertSee('value="Kopi Nusantara"', false);
});

test('UMKM can replace their profile picture from the business profile editor', function () {
    Storage::fake('local');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);
    $user = User::factory()->create(['role' => 'umkm']);
    $profile = BusinessProfile::factory()->for($user)->create([
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kopi Nusantara',
            'category' => 'Makanan dan minuman',
            'location' => 'Bandung',
            'product_type' => 'Kopi bubuk',
            'confirm_profile' => '1',
            'profile_image' => UploadedFile::fake()->createWithContent('profile.png', $png),
            'cover_image' => UploadedFile::fake()->createWithContent('cover.png', $png),
        ])
        ->assertRedirect(route('business-profile.show'));

    $profile = $profile->fresh();
    $oldProfileImagePath = $profile->profile_image_path;
    $oldCoverImagePath = $profile->cover_image_path;
    Storage::disk('local')->assertExists($oldProfileImagePath);
    Storage::disk('local')->assertExists($oldCoverImagePath);

    $this->actingAs($user)
        ->get(route('business-profile.image', [$profile, 'profile']))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kopi Nusantara',
            'category' => 'Makanan dan minuman',
            'location' => 'Bandung',
            'product_type' => 'Kopi bubuk',
            'confirm_profile' => '1',
            'profile_image' => UploadedFile::fake()->createWithContent('new-profile.png', $png),
        ])
        ->assertRedirect(route('business-profile.show'));

    $profile = $profile->fresh();
    expect($profile->profile_image_path)->not->toBe($oldProfileImagePath)
        ->and($profile->cover_image_path)->toBe($oldCoverImagePath);
    Storage::disk('local')->assertMissing($oldProfileImagePath);
    Storage::disk('local')->assertExists($profile->profile_image_path);
    Storage::disk('local')->assertExists($oldCoverImagePath);

    $this->actingAs($user)
        ->get(route('business-profile.show'))
        ->assertOk()
        ->assertSee(route('business-profile.image', [$profile, 'profile']), false);
});

test('updating vendor images does not invalidate their approved verification', function () {
    Storage::fake('local');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);
    $vendor = User::factory()->create(['role' => 'vendor']);
    $profile = BusinessProfile::factory()->for($vendor)->create([
        'business_name' => 'Kemasan Prima',
        'category' => 'Manufaktur kemasan',
        'location' => 'Jakarta',
        'product_categories' => 'Pouch dan botol',
        'minimum_order_quantity' => 500,
        'verification_status' => 'approved',
        'completed_at' => now(),
    ]);

    $this->actingAs($vendor)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => $profile->business_name,
            'category' => $profile->category,
            'location' => $profile->location,
            'product_categories' => $profile->product_categories,
            'minimum_order_quantity' => $profile->minimum_order_quantity,
            'confirm_profile' => '1',
            'profile_image' => UploadedFile::fake()->createWithContent('vendor-profile.png', $png),
            'cover_image' => UploadedFile::fake()->createWithContent('vendor-cover.png', $png),
        ])
        ->assertRedirect(route('business-profile.show'));

    expect($profile->fresh()->verification_status)->toBe('approved');
});

test('UMKM cannot finish onboarding without confirming a complete profile', function () {
    $user = User::factory()->create(['role' => 'umkm']);
    $profile = $user->businessProfile()->create([]);

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Kopi Nusantara',
            'category' => 'Makanan dan minuman',
            'location' => 'Bandung',
            'product_type' => 'Kopi bubuk',
        ])
        ->assertSessionHasErrors('confirm_profile');

    expect($profile->fresh()->completed_at)->toBeNull();
});

test('vendors must provide MOQ and can complete their profile in one submission', function () {
    $user = User::factory()->create(['role' => 'vendor']);
    $profile = $user->businessProfile()->create([]);
    $profileData = [
        'business_name' => 'Kemasan Prima',
        'category' => 'Manufaktur kemasan',
        'location' => 'Jakarta',
        'product_categories' => 'Pouch dan botol',
        'minimum_order_quantity' => '500',
        'materials' => 'Kraft, PET',
        'monthly_capacity' => '12000',
        'production_lead_time_days' => '14',
        'confirm_profile' => '1',
    ];

    $this->actingAs($user)
        ->post(route('onboarding.update', ['step' => 1]), array_merge($profileData, [
            'minimum_order_quantity' => '0',
        ]))
        ->assertSessionHasErrors('minimum_order_quantity');

    expect($profile->fresh()->completed_at)->toBeNull();

    $this->post(route('onboarding.update', ['step' => 1]), $profileData)
        ->assertRedirect(route('business-profile.show'));

    $profile = $user->fresh()->businessProfile;

    expect($profile->product_categories)->toBe('Pouch dan botol')
        ->and($profile->minimum_order_quantity)->toBe(500)
        ->and($profile->monthly_capacity)->toBe(12000)
        ->and($profile->production_lead_time_days)->toBe(14)
        ->and($profile->completed_at)->not->toBeNull();
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
