<?php

use App\Models\BusinessProfile;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admins can open the dashboard and review platform statistics', function () {
    $admin = User::factory()->create(['name' => 'Navora', 'role' => 'admin']);
    User::factory()->create(['role' => 'umkm']);

    $pendingVendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($pendingVendor)->create([
        'business_name' => 'Vendor Menunggu',
        'verification_status' => 'pending',
        'verification_submitted_at' => now(),
    ]);

    $approvedVendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($approvedVendor)->create([
        'verification_status' => 'approved',
    ]);

    Feedback::query()->create([
        'topic' => 'navora',
        'message' => 'Dashboard mudah digunakan.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Selamat datang, Navora')
        ->assertSee('UMKM terdaftar')
        ->assertSee('Vendor terdaftar')
        ->assertSee('Menunggu verifikasi')
        ->assertSee('Pusat kendali')
        ->assertSee('Ringkasan platform')
        ->assertSee('PERLU TINDAK LANJUT')
        ->assertSee('Akses cepat')
        ->assertSee('Paket dan iklan')
        ->assertSee('Vendor Menunggu')
        ->assertSee('Tinjau pengajuan')
        ->assertSee(route('admin.dashboard'), false)
        ->assertSee(route('admin.vendor-verifications.index'), false)
        ->assertSee(route('admin.feedback.index'), false);
});

test('only admins can open the admin dashboard', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $user = User::factory()->create(['role' => 'umkm']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
