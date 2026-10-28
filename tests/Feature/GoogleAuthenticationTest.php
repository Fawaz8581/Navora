<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-client-id',
        'services.google.client_secret' => 'google-client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
});

test('google sign-in redirects to google and stores a one-time state and selected role', function () {
    $response = $this->get(route('auth.google.redirect', ['role' => 'vendor']));
    $location = $response->headers->get('Location');

    $response->assertRedirect();

    parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

    expect(parse_url((string) $location, PHP_URL_HOST))->toBe('accounts.google.com')
        ->and($query['client_id'])->toBe('google-client-id')
        ->and($query['redirect_uri'])->toBe('http://localhost/auth/google/callback')
        ->and($query['state'])->toBe(session('google_oauth_state'))
        ->and(session('google_oauth_role'))->toBe('vendor');
});

test('google sign-in explains when oauth credentials are not configured', function () {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    $this->from(route('login'))
        ->get(route('auth.google.redirect'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Google sign-in belum aktif. Lengkapi kredensial Google OAuth di file .env terlebih dahulu.');
});

test('a callback with an invalid state is rejected without contacting google', function () {
    Http::preventStrayRequests();

    $this->withSession(['google_oauth_state' => Str::random(40)])
        ->get(route('auth.google.callback', ['state' => 'invalid-state', 'code' => 'authorization-code']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Permintaan masuk Google tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('google sign-in creates a verified account using the selected role', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-123',
            'email' => 'Vendor@Example.com',
            'email_verified' => true,
            'name' => 'Google Vendor',
        ]),
    ]);

    $this->withSession([
        'google_oauth_state' => 'valid-oauth-state',
        'google_oauth_role' => 'vendor',
    ])
        ->get(route('auth.google.callback', [
            'state' => 'valid-oauth-state',
            'code' => 'authorization-code',
        ]))
        ->assertRedirect(route('workspace'));

    $user = User::where('email', 'vendor@example.com')->firstOrFail();

    expect($user->role)->toBe('vendor')
        ->and($user->google_id)->toBe('google-user-123')
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('google sign-in does not create an account when its email is unverified', function () {
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-456',
            'email' => 'unverified@example.com',
            'email_verified' => false,
            'name' => 'Unverified User',
        ]),
    ]);

    $this->withSession(['google_oauth_state' => 'valid-oauth-state'])
        ->get(route('auth.google.callback', [
            'state' => 'valid-oauth-state',
            'code' => 'authorization-code',
        ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Akun Google harus memiliki alamat email terverifikasi untuk digunakan di Navora.');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('google sign-in links a verified matching email without changing its existing role', function () {
    $user = User::factory()->create([
        'email' => 'member@example.com',
        'role' => 'partner',
        'google_id' => null,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-789',
            'email' => 'member@example.com',
            'email_verified' => true,
            'name' => 'Google Member',
        ]),
    ]);

    $this->withSession(['google_oauth_state' => 'valid-oauth-state'])
        ->get(route('auth.google.callback', [
            'state' => 'valid-oauth-state',
            'code' => 'authorization-code',
        ]))
        ->assertRedirect(route('workspace'));

    expect($user->fresh()->google_id)->toBe('google-user-789')
        ->and($user->fresh()->role)->toBe('partner');

    $this->assertAuthenticatedAs($user);
});
