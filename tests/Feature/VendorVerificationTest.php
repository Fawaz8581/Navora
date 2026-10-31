<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fakePngUpload(string $name): UploadedFile
{
    $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=', true);

    if ($content === false) {
        throw new RuntimeException('The PNG test fixture could not be decoded.');
    }

    return UploadedFile::fake()->createWithContent($name, $content);
}

test('vendors can submit their NIB and verification documents for admin review', function () {
    Storage::fake('local');
    $vendor = User::factory()->create(['role' => 'vendor']);
    $profile = BusinessProfile::factory()->for($vendor)->create();

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [
            'nib' => '1234567890123',
            'nib_document' => UploadedFile::fake()->create('nib.pdf', 20, 'application/pdf'),
            'work_samples' => [fakePngUpload('sample.png')],
            'production_photos' => [fakePngUpload('production.png')],
            'certificates' => [UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf')],
        ])
        ->assertRedirect(route('vendor.verification.index'))
        ->assertSessionHas('status');

    $profile = $profile->fresh();

    expect($profile->nib)->toBe('1234567890123')
        ->and(DB::table('business_profiles')->where('id', $profile->id)->value('nib'))->not->toBe('1234567890123')
        ->and($profile->verification_status)->toBe('pending')
        ->and($profile->verification_submitted_at)->not->toBeNull()
        ->and(count($profile->work_sample_paths))->toBe(1)
        ->and(count($profile->production_photo_paths))->toBe(1)
        ->and(count($profile->certificate_paths))->toBe(1);

    Storage::disk('local')->assertExists($profile->nib_document_path);
    Storage::disk('local')->assertExists($profile->work_sample_paths[0]);
    Storage::disk('local')->assertExists($profile->production_photo_paths[0]);
    Storage::disk('local')->assertExists($profile->certificate_paths[0]);

    $this->actingAs($vendor)
        ->get(route('vendor.verification.index'))
        ->assertOk()
        ->assertSee('Menunggu pemeriksaan')
        ->assertDontSee('name="work_samples[]"', false);

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertRedirect(route('vendor.verification.index'));

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [])
        ->assertStatus(409);
});

test('verification documents are only downloadable by an admin and admins can approve a vendor', function () {
    Storage::fake('local');
    $vendor = User::factory()->create(['role' => 'vendor']);
    $profile = BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'pending',
        'nib_document_path' => 'vendor-verification/nib.pdf',
        'work_sample_paths' => ['vendor-verification/sample.png'],
        'production_photo_paths' => ['vendor-verification/production.png'],
    ]);
    Storage::disk('local')->put($profile->nib_document_path, 'private NIB');

    $this->actingAs($vendor)
        ->get(route('admin.vendor-verifications.documents', [$profile, 'nib', 0]))
        ->assertStatus(403);

    $admin = User::factory()->create(['name' => 'Navora Admin', 'role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.vendor-verifications.index'))
        ->assertOk()
        ->assertSee($vendor->name);

    $this->actingAs($admin)
        ->get(route('admin.vendor-verifications.show', $profile))
        ->assertOk()
        ->assertSee('Dokumen NIB')
        ->assertSee('Setujui vendor');

    $this->actingAs($admin)
        ->get(route('admin.vendor-verifications.documents', [$profile, 'nib', 0]))
        ->assertOk();

    $this->actingAs($admin)
        ->post(route('admin.vendor-verifications.review', $profile), [
            'decision' => 'approved',
        ])
        ->assertRedirect(route('admin.vendor-verifications.show', $profile))
        ->assertSessionHas('status');

    expect($profile->fresh()->verification_status)->toBe('approved')
        ->and($profile->fresh()->verified_by)->toBe($admin->id)
        ->and($profile->fresh()->verification_reviewed_at)->not->toBeNull();

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertOk();

    $this->actingAs($vendor)
        ->get(route('vendor.verification.index'))
        ->assertOk()
        ->assertSee('Terverifikasi')
        ->assertDontSee('name="work_samples[]"', false);

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [])
        ->assertStatus(409);
});

test('admin rejection requires a reason and vendors can resubmit after review', function () {
    Storage::fake('local');
    $vendor = User::factory()->create(['role' => 'vendor']);
    $profile = BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'pending',
        'nib_document_path' => 'vendor-verification/nib.pdf',
        'work_sample_paths' => [
            'vendor-verification/sample-1.png',
            'vendor-verification/sample-2.png',
            'vendor-verification/sample-3.png',
            'vendor-verification/sample-4.png',
            'vendor-verification/sample-5.png',
        ],
        'production_photo_paths' => [
            'vendor-verification/production-1.png',
            'vendor-verification/production-2.png',
            'vendor-verification/production-3.png',
            'vendor-verification/production-4.png',
            'vendor-verification/production-5.png',
        ],
        'certificate_paths' => [
            'vendor-verification/certificate-1.pdf',
            'vendor-verification/certificate-2.pdf',
            'vendor-verification/certificate-3.pdf',
            'vendor-verification/certificate-4.pdf',
            'vendor-verification/certificate-5.pdf',
        ],
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->post(route('admin.vendor-verifications.review', $profile), [
            'decision' => 'rejected',
        ])
        ->assertSessionHasErrors('verification_notes');

    $this->actingAs($admin)
        ->post(route('admin.vendor-verifications.review', $profile), [
            'decision' => 'rejected',
            'verification_notes' => 'Foto tempat produksi belum terlihat jelas.',
        ])
        ->assertRedirect(route('admin.vendor-verifications.show', $profile));

    expect($profile->fresh()->verification_status)->toBe('rejected')
        ->and($profile->fresh()->verification_notes)->toBe('Foto tempat produksi belum terlihat jelas.');

    $this->actingAs($vendor)
        ->get(route('vendor.verification.index'))
        ->assertOk()
        ->assertSee('Akun vendor belum terverifikasi.')
        ->assertSee('Alasan penolakan')
        ->assertSee('Foto tempat produksi belum terlihat jelas.');

    $this->actingAs($vendor)
        ->get(route('business-profile.show'))
        ->assertOk()
        ->assertSee($profile->business_name);

    $this->actingAs($vendor)
        ->get(route('workspace'))
        ->assertRedirect(route('vendor.verification.index'));

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [
            'nib' => '1234567890123',
        ])
        ->assertSessionHasErrors('documents');

    expect($profile->fresh()->verification_status)->toBe('rejected');

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [
            'nib' => '1234567890123',
            'nib_document' => UploadedFile::fake()->create('nib-updated.pdf', 20, 'application/pdf'),
            'work_samples' => [fakePngUpload('sample-2.png')],
            'production_photos' => [fakePngUpload('production-2.png')],
            'certificates' => [UploadedFile::fake()->create('certificate-updated.pdf', 20, 'application/pdf')],
        ])
        ->assertRedirect(route('vendor.verification.index'));

    $profile = $profile->fresh();

    expect($profile->verification_status)->toBe('pending')
        ->and($profile->verification_notes)->toBeNull()
        ->and(count($profile->work_sample_paths))->toBe(1)
        ->and(count($profile->production_photo_paths))->toBe(1)
        ->and(count($profile->certificate_paths))->toBe(1);
});

test('changing verified vendor details returns verification to the submission state', function () {
    $vendor = User::factory()->create(['role' => 'vendor']);
    $admin = User::factory()->create(['role' => 'admin']);
    $profile = BusinessProfile::factory()->for($vendor)->create([
        'verification_status' => 'approved',
        'verification_notes' => 'Approved',
        'verification_submitted_at' => now()->subDay(),
        'verification_reviewed_at' => now(),
        'verified_by' => $admin->id,
    ]);

    $this->actingAs($vendor)
        ->post(route('onboarding.update', ['step' => 1]), [
            'business_name' => 'Nama usaha baru',
            'phone' => $profile->phone,
            'category' => $profile->category,
            'location' => $profile->location,
            'description' => $profile->description,
            'product_categories' => $profile->product_categories,
            'materials' => $profile->materials,
            'minimum_order_quantity' => $profile->minimum_order_quantity,
            'monthly_capacity' => $profile->monthly_capacity,
            'production_lead_time_days' => $profile->production_lead_time_days,
            'confirm_profile' => '1',
        ])
        ->assertRedirect(route('business-profile.show'));

    expect($profile->fresh()->verification_status)->toBe('not_submitted')
        ->and($profile->fresh()->verification_notes)->toBeNull()
        ->and($profile->fresh()->verification_submitted_at)->toBeNull()
        ->and($profile->fresh()->verified_by)->toBeNull();
});

test('verification routes enforce user roles and required document data', function () {
    $umkm = User::factory()->create(['role' => 'umkm']);
    $vendor = User::factory()->create(['role' => 'vendor']);
    BusinessProfile::factory()->for($vendor)->create();

    $this->get(route('admin.vendor-verifications.index'))->assertRedirect(route('login'));

    $this->actingAs($umkm)
        ->get(route('admin.vendor-verifications.index'))
        ->assertStatus(403);

    $this->actingAs($umkm)
        ->get(route('vendor.verification.index'))
        ->assertStatus(403);

    $this->actingAs($vendor)
        ->post(route('vendor.verification.submit'), [])
        ->assertSessionHasErrors(['nib', 'nib_document', 'work_samples', 'production_photos']);
});

test('admin login opens the admin dashboard', function () {
    $admin = User::factory()->create([
        'email' => 'navora-admin@example.com',
        'role' => 'admin',
        'password' => Hash::make('secure-admin-password'),
    ]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'secure-admin-password',
    ])->assertRedirect(route('admin.dashboard'));
});
