<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('local setup URL creates the configured admin and reports when it already exists', function () {
    config([
        'app.env' => 'testing',
        'services.navora_admin.email' => 'Navora@gmail.com',
        'services.navora_admin.password' => 'Navora123',
    ]);

    $this->get(route('admin-account-setup.create'))
        ->assertOk()
        ->assertSee('Admin account successfully created')
        ->assertSee('Navora@gmail.com');

    $admin = User::where('email', 'navora@gmail.com')->firstOrFail();

    expect($admin->name)->toBe('Navora')
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('Navora123', $admin->password))->toBeTrue();

    $this->get(route('admin-account-setup.create'))
        ->assertOk()
        ->assertSee('Admin account already created');

    expect(User::where('email', 'navora@gmail.com')->count())->toBe(1);
});

test('setup URL reports when the configured email belongs to a non-admin account', function () {
    config([
        'app.env' => 'testing',
        'services.navora_admin.email' => 'Navora@gmail.com',
        'services.navora_admin.password' => 'Navora123',
    ]);

    User::factory()->create([
        'email' => 'navora@gmail.com',
        'role' => 'umkm',
    ]);

    $this->get(route('admin-account-setup.create'))
        ->assertConflict();
});
