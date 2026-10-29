<?php

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows password-confirmed account deletion in account settings', function () {
    $user = User::factory()->create();
    BusinessProfile::factory()->for($user)->create([
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('workspace', ['tab' => 'account']))
        ->assertOk()
        ->assertSee(route('account.destroy'), false)
        ->assertSee('name="password"', false)
        ->assertSee('Hapus akun secara permanen');
});

it('offers account deletion before onboarding is complete', function () {
    $user = User::factory()->create();
    BusinessProfile::factory()->for($user)->create([
        'completed_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.step', ['step' => 1]))
        ->assertOk()
        ->assertSee(route('account.destroy'), false)
        ->assertSee('name="password"', false)
        ->assertSee('Hapus akun secara permanen');
});

it('deletes the authenticated account and its verification files with confirmation', function () {
    Storage::fake('local');
    $user = User::factory()->create(['password' => 'current-password']);
    $profile = BusinessProfile::factory()->for($user)->create([
        'completed_at' => now(),
    ]);
    $documentPath = 'vendor-verification/'.$profile->id.'/nib.pdf';
    $profile->update(['nib_document_path' => $documentPath]);
    Storage::disk('local')->put($documentPath, 'private document');

    $this->actingAs($user)
        ->from(route('workspace', ['tab' => 'account']))
        ->delete(route('account.destroy'), ['password' => 'current-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Akun Navora kamu berhasil dihapus.');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('business_profiles', ['id' => $profile->id]);
    Storage::disk('local')->assertMissing($documentPath);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Akun Navora kamu berhasil dihapus.');
});

it('keeps the account and returns actionable feedback when the password is incorrect', function () {
    $user = User::factory()->create(['password' => 'current-password']);
    $profile = BusinessProfile::factory()->for($user)->create([
        'completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('workspace', ['tab' => 'account']))
        ->delete(route('account.destroy'), ['password' => 'wrong-password'])
        ->assertRedirect(route('workspace', ['tab' => 'account']))
        ->assertSessionHasErrors([
            'password' => 'Kata sandi yang dimasukkan tidak sesuai.',
        ]);

    $this->assertAuthenticatedAs($user);
    $this->assertModelExists($user);
    $this->assertModelExists($profile);

    $this->get(route('workspace', ['tab' => 'account']))
        ->assertOk()
        ->assertSee('Kata sandi yang dimasukkan tidak sesuai.');
});

it('requires authentication before deleting an account', function () {
    $this->delete(route('account.destroy'), ['password' => 'current-password'])
        ->assertRedirect(route('login'));
});
