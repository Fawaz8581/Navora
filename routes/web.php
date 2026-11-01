<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAccountSetupController;
use App\Http\Controllers\Admin\AdvertisementController as AdminAdvertisementController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\VendorListingController as AdminVendorListingController;
use App\Http\Controllers\Admin\VendorVerificationController as AdminVendorVerificationController;
use App\Http\Controllers\AdvertisementAssetController;
use App\Http\Controllers\AdvertisementClickController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMessageController;
use App\Http\Controllers\VendorAdvertisementController;
use App\Http\Controllers\VendorCatalogController;
use App\Http\Controllers\VendorListingController;
use App\Http\Controllers\VendorVerificationController;
use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/masukan', [FeedbackController::class, 'create'])->name('feedback.create');
Route::post('/masukan', [FeedbackController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('feedback.store');

Route::get('/', function () {
    return view('home');
})->name('home');
Route::get('/iklan/{adCampaign}/aset', [AdvertisementAssetController::class, 'show'])
    ->name('advertisements.asset');
Route::get('/iklan/{adCampaign}/klik', AdvertisementClickController::class)
    ->name('advertisements.click');

Route::get('/ruang-kerja', function (Request $request, VendorAdvertisementController $vendorAdvertisements) {
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

    $workspaceData = $user?->role === 'vendor'
        ? $vendorAdvertisements->workspaceData($request)
        : [];

    return view('workspace', [
        'advertisementNotices' => $user?->role === 'vendor'
            ? $user->adCampaigns()
                ->whereIn('status', [AdCampaign::STATUS_ACTIVE, AdCampaign::STATUS_REJECTED])
                ->whereNotNull('reviewed_at')
                ->latest('reviewed_at')
                ->limit(5)
                ->get()
            : collect(),
        ...$workspaceData,
    ]);
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
    Route::get('/vendor/katalog-saya', [VendorListingController::class, 'index'])->name('vendor.listings.index');
    Route::post('/vendor/katalog-saya', [VendorListingController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('vendor.listings.store');
    Route::get('/katalog/vendor/{businessProfile}/foto/{index}', [VendorCatalogController::class, 'showSample'])
        ->whereNumber('index')
        ->name('vendors.catalog.image');
    Route::get('/katalog/vendor/produk/{vendorListing}/media/{vendorListingMedia}', [VendorCatalogController::class, 'showListingMedia'])
        ->name('vendors.catalog.listing-media');
    Route::get('/pesanan', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/vendor/pendapatan', [OrderController::class, 'vendorIncome'])->name('orders.vendor-income');
    Route::post('/pesanan', [OrderController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('orders.store');
    Route::post('/pesanan/penawaran', [OrderController::class, 'storeQuote'])
        ->middleware('throttle:20,1')
        ->name('orders.quote');
    Route::get('/pesanan/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/pesanan/{order}/pesan', [OrderMessageController::class, 'index'])
        ->name('orders.messages.index');
    Route::post('/pesanan/{order}/pesan', [OrderMessageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('orders.messages.store');
    Route::post('/pesanan/{order}/penawaran-harga', [OrderController::class, 'setPrice'])
        ->middleware('throttle:10,1')
        ->name('orders.price');
    Route::post('/pesanan/{order}/penawaran/keputusan', [OrderController::class, 'decideQuote'])
        ->middleware('throttle:10,1')
        ->name('orders.quote-decision');
    Route::post('/pesanan/{order}/pembayaran', [OrderController::class, 'checkout'])
        ->middleware('throttle:10,1')
        ->name('orders.checkout');
    Route::post('/pesanan/{order}/pembayaran/periksa', [OrderController::class, 'refreshPayment'])
        ->middleware('throttle:20,1')
        ->name('orders.payment-refresh');
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
    Route::get('/profil-usaha/{businessProfile}/gambar/{type}', [BusinessProfileController::class, 'showImage'])
        ->where('type', 'profile|cover')
        ->name('business-profile.image');

    Route::get('/vendor/verifikasi', [VendorVerificationController::class, 'index'])->name('vendor.verification.index');
    Route::post('/vendor/verifikasi', [VendorVerificationController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->name('vendor.verification.submit');

    Route::get('/vendor/iklan', [VendorAdvertisementController::class, 'index'])
        ->name('vendor.advertisements.index');
    Route::post('/vendor/iklan', [VendorAdvertisementController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('vendor.advertisements.store');
    Route::post('/vendor/iklan/{adCampaign}/aset', [VendorAdvertisementController::class, 'uploadAsset'])
        ->middleware('throttle:10,1')
        ->name('vendor.advertisements.asset');
    Route::post('/vendor/iklan/{adCampaign}/edit', [VendorAdvertisementController::class, 'updateCampaign'])
        ->middleware('throttle:10,1')
        ->name('vendor.advertisements.update');
    Route::post('/iklan/{adCampaign}/cek-pembayaran', [MidtransNotificationController::class, 'refreshAdvertisement'])
        ->middleware('throttle:20,1')
        ->name('vendor.advertisements.refresh');

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

    Route::prefix('admin/vendor-listings')
        ->name('admin.vendor-listings.')
        ->middleware('admin')
        ->group(function () {
            Route::get('/', [AdminVendorListingController::class, 'index'])->name('index');
            Route::post('/{vendorListing}/approve', [AdminVendorListingController::class, 'approve'])
                ->name('approve');
        });

    Route::get('/admin', AdminDashboardController::class)
        ->middleware('admin')
        ->name('admin.dashboard');

    Route::get('/admin/feedback', [AdminFeedbackController::class, 'index'])
        ->middleware('admin')
        ->name('admin.feedback.index');

    Route::prefix('admin/customize')
        ->name('admin.customize.')
        ->middleware('admin')
        ->group(function () {
            Route::get('/', [AdminAdvertisementController::class, 'index'])->name('index');
            Route::post('/slot-settings', [AdminAdvertisementController::class, 'updateSlotSettings'])
                ->name('slot-settings.update');
            Route::post('/campaigns/{adCampaign}/review', [AdminAdvertisementController::class, 'reviewCampaign'])
                ->middleware('throttle:10,1')
                ->name('campaigns.review');
            Route::post('/campaigns/{adCampaign}/refund/retry', [AdminAdvertisementController::class, 'retryRefund'])
                ->middleware('throttle:10,1')
                ->name('campaigns.refund.retry');
            Route::get('/campaigns/{adCampaign}/asset', [AdvertisementAssetController::class, 'showForAdmin'])->name('campaigns.asset');
        });
});
