<?php

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets visitors send feedback about Navora and confirms when it is saved', function () {
    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertSee('Ceritakan pengalamanmu')
        ->assertSee('Platform Navora')
        ->assertSee('Vendor di Navora');

    $this->post(route('feedback.store'), [
        'topic' => 'navora',
        'message' => 'Saya berharap pilihan vendor bisa difilter berdasarkan lokasi.',
    ])
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHas('status', 'Terima kasih, masukanmu sudah tersimpan dan akan membantu Navora berkembang.');

    $feedback = Feedback::sole();

    expect($feedback->topic)->toBe('navora')
        ->and($feedback->vendor_name)->toBeNull()
        ->and($feedback->user_id)->toBeNull();

    $this->get(route('feedback.create'))
        ->assertOk()
        ->assertSee('Terima kasih, masukanmu sudah tersimpan dan akan membantu Navora berkembang.');
});

it('saves vendor feedback with a rating and the signed-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('feedback.store'), [
            'topic' => 'vendor',
            'vendor_name' => 'PouchKita Studio',
            'rating' => '4',
            'message' => 'Informasi MOQ vendor ini cukup jelas dan membantu.',
        ])
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHas('status');

    $feedback = Feedback::sole();

    expect($feedback->topic)->toBe('vendor')
        ->and($feedback->vendor_name)->toBe('PouchKita Studio')
        ->and($feedback->rating)->toBe(4)
        ->and($feedback->user_id)->toBe($user->id);
});

it('rejects vendor feedback without a vendor name or a useful message', function () {
    $this->from(route('feedback.create'))
        ->post(route('feedback.store'), [
            'topic' => 'vendor',
            'message' => 'Bagus',
        ])
        ->assertRedirect(route('feedback.create'))
        ->assertSessionHasErrors([
            'vendor_name' => 'Masukkan nama vendor yang ingin kamu beri masukan.',
            'message' => 'Masukan minimal berisi 10 karakter agar tim kami dapat memahaminya.',
        ]);

    expect(Feedback::query()->count())->toBe(0);
});

it('shows submitted feedback only to administrators', function () {
    $user = User::factory()->create();
    $message = "Vendor <script>alert('masukan')</script> perlu menjelaskan ongkos kirim.";
    Feedback::create([
        'user_id' => $user->id,
        'topic' => 'vendor',
        'vendor_name' => 'PouchKita Studio',
        'rating' => 4,
        'message' => $message,
    ]);

    $this->get(route('admin.feedback.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->get(route('admin.feedback.index'))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.feedback.index'))
        ->assertOk()
        ->assertSee('Masukan pengguna')
        ->assertSee('PouchKita Studio')
        ->assertSee($user->name)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>', false);
});

it('keeps saved feedback while anonymizing it when its user deletes their account', function () {
    $user = User::factory()->create(['password' => 'current-password']);
    $feedback = Feedback::create([
        'user_id' => $user->id,
        'topic' => 'navora',
        'message' => 'Saya berharap bisa menyimpan lebih banyak pilihan vendor.',
    ]);

    $this->actingAs($user)
        ->delete(route('account.destroy'), ['password' => 'current-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect($feedback->fresh()->user_id)->toBeNull();
    $this->assertModelExists($feedback);
});
