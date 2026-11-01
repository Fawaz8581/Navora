<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the workspace route renders the Navora marketplace demo and its assets', function () {
    $this->get('/ruang-kerja')
        ->assertOk()
        ->assertSee('Ruang Navora — Marketplace kemasan UMKM', false)
        ->assertSee('Cari vendor')
        ->assertSee('Patungan MOQ')
        ->assertSee('Pesanan dan pelacakan')
        ->assertSee('Katalog baru ditinjau admin sebelum aktif.')
        ->assertSee('Katalog aktif dapat diedit tanpa ACC ulang.')
        ->assertSee('Perubahan katalog yang sudah aktif langsung ditampilkan tanpa ACC ulang.')
        ->assertSee('Masuk untuk mengajukan')
        ->assertSee(route('orders.index'), false)
        ->assertSee('Permintaan penawaran')
        ->assertSee('Asisten kemasan')
        ->assertSee('PouchKita Studio')
        ->assertSee(asset('images/products/coffee.jpg'), false)
        ->assertSee(asset('images/products/cardboard.jpg'), false)
        ->assertSee(asset('images/products/bottle.jpg'), false)
        ->assertSee('data-page-panel="overview"', false)
        ->assertSee('data-page-panel="vendors"', false)
        ->assertSee('data-role-select', false)
        ->assertSee(route('feedback.create'), false)
        ->assertSee('/css/navora-app.css', false)
        ->assertSee('/js/navora-app.js', false);
});

test('a signed-in user sees the workspace for their saved role without the demo role selector', function () {
    $vendor = User::factory()->create([
        'name' => 'Vendor Navora',
        'role' => 'vendor',
    ]);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
    ]);

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk()
        ->assertSee('data-auth-role="vendor"', false)
        ->assertSee('data-auth-name="Vendor Navora"', false)
        ->assertSee('Katalog baru ditinjau admin sebelum aktif.')
        ->assertSee('Katalog aktif dapat diedit tanpa ACC ulang.')
        ->assertSee('Perubahan katalog yang sudah aktif langsung ditampilkan tanpa ACC ulang.')
        ->assertSee('Vendor')
        ->assertDontSee('data-role-select', false);
});

test('vendors without approved verification can only continue through the verification flow', function () {
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'rejected',
        'verification_notes' => 'Perbarui foto tempat produksi.',
    ]);

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertRedirect(route('vendor.verification.index'));

    $this->actingAs($vendor)
        ->get(route('vendor.verification.index'))
        ->assertOk()
        ->assertSee('Perbarui foto tempat produksi.')
        ->assertSee('Kirim ulang verifikasi');
});
