<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guests can view the login and registration pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Masuk ke Navora')
        ->assertSee('Masuk dengan Google')
        ->assertSee(route('auth.google.redirect'), false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Buat akun Navora')
        ->assertSee('Pemilik brand / UMKM')
        ->assertSee('Vendor kemasan atau maklon')
        ->assertSee('Mitra pendukung UMKM');
});

test('a visitor can register and is authenticated with their selected account type', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Navora Seller',
        'email' => '  SELLER@GMAIL.COM ',
        'role' => 'vendor',
        'password' => 'secure-password-123',
        'password_confirmation' => 'secure-password-123',
    ]);

    $user = User::where('email', 'seller@gmail.com')->firstOrFail();

    $response->assertRedirect(route('workspace'));

    expect($user->role)->toBe('vendor')
        ->and(Hash::check('secure-password-123', $user->password))->toBeTrue()
        ->and(auth()->id())->toBe($user->id);
});

test('registration rejects duplicate email addresses and weak passwords', function () {
    User::factory()->create(['email' => 'existing@gmail.com']);

    $this->from(route('register'))
        ->post(route('register.store'), [
            'name' => 'New User',
            'email' => 'EXISTING@gmail.com',
            'role' => 'umkm',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email', 'password']);

    expect(User::count())->toBe(1);
});

test('a registered user can log in and log out', function () {
    $user = User::factory()->create([
        'email' => 'member@gmail.com',
        'password' => Hash::make('secure-password-123'),
    ]);

    $this->post(route('login.store'), [
        'email' => 'MEMBER@gmail.com',
        'password' => 'secure-password-123',
        'remember' => '1',
    ])
        ->assertRedirect(route('workspace'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

test('login rejects an incorrect password', function () {
    User::factory()->create([
        'email' => 'member@gmail.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'email' => 'member@gmail.com',
            'password' => 'incorrect-password',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
