<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BusinessProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/ruang-kerja', function () {
    $user = auth()->user();

    if ($user && in_array($user->role, ['umkm', 'vendor'], true) && ! $user->businessProfile?->completed_at) {
        return redirect()->route('onboarding.index');
    }

    return view('workspace');
})->name('workspace');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [BusinessProfileController::class, 'index'])->name('onboarding.index');
    Route::get('/onboarding/{step}', [BusinessProfileController::class, 'show'])
        ->whereNumber('step')
        ->name('onboarding.step');
    Route::post('/onboarding/{step}', [BusinessProfileController::class, 'update'])
        ->whereNumber('step')
        ->name('onboarding.update');
    Route::get('/profil-usaha', [BusinessProfileController::class, 'profile'])->name('business-profile.show');
});
