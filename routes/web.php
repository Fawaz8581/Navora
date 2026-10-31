<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAccountSetupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\VendorVerificationController as AdminVendorVerificationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\VendorCatalogController;
use App\Http\Controllers\VendorVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/masukan', [FeedbackController::class, 'create'])->name('feedback.create');
Route::post('/masukan', [FeedbackController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('feedback.store');

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/ruang-kerja', function () {
    $user = auth()->user();

    if ($user?->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    if ($user && in_array($user->role, ['umkm', 'vendor'], true)) {
        $profile = $user->businessProfile;

        if (! $profile?->completed_at) {
            return redirect()->route('onboarding.index');
        }

        if ($user->role === 'vendor' && $profile->verification_status !== 'approved') {
            return redirect()->route('vendor.verification.index');
        }
    }

    return view('workspace');
})->name('workspace');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::get('/adminaccountcreate', [AdminAccountSetupController::class, 'create'])
    ->middleware('throttle:5,1')
    ->name('admin-account-setup.create');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/katalog/vendor', VendorCatalogController::class)->name('vendors.catalog');
    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/pesanan', [OrderController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('orders.store');
    Route::post('/pesanan/penawaran', [OrderController::class, 'storeQuote'])
        ->middleware('throttle:20,1')
        ->name('orders.quote');
    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/pesanan/{order}/peserta', [OrderController::class, 'join'])
        ->middleware('throttle:20,1')
        ->name('orders.join');
    Route::patch('/pesanan/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('orders.status');

    Route::delete('/akun', AccountController::class)
        ->middleware('throttle:6,1')
        ->name('account.destroy');

    Route::get('/onboarding', [BusinessProfileController::class, 'index'])->name('onboarding.index');
    Route::get('/onboarding/{step}', [BusinessProfileController::class, 'show'])
        ->whereNumber('step')
        ->name('onboarding.step');
    Route::post('/onboarding/{step}', [BusinessProfileController::class, 'update'])
        ->whereNumber('step')
        ->name('onboarding.update');
    Route::get('/profil-usaha', [BusinessProfileController::class, 'profile'])->name('business-profile.show');

    Route::get('/vendor/verifikasi', [VendorVerificationController::class, 'index'])->name('vendor.verification.index');
    Route::post('/vendor/verifikasi', [VendorVerificationController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('vendor.verification.submit');

    Route::prefix('admin/vendor-verifications')
        ->name('admin.vendor-verifications.')
        ->middleware('admin')
        ->group(function () {
            Route::get('/', [AdminVendorVerificationController::class, 'index'])->name('index');
            Route::get('/{businessProfile}', [AdminVendorVerificationController::class, 'show'])->name('show');
            Route::post('/{businessProfile}/review', [AdminVendorVerificationController::class, 'review'])->name('review');
            Route::get('/{businessProfile}/documents/{type}/{index}', [AdminVendorVerificationController::class, 'download'])
                ->whereNumber('index')
                ->name('documents');
        });

    Route::get('/admin', AdminDashboardController::class)
        ->middleware('admin')
        ->name('admin.dashboard');

    Route::get('/admin/feedback', [AdminFeedbackController::class, 'index'])
        ->middleware('admin')
        ->name('admin.feedback.index');
});
