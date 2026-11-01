<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\BusinessProfile;
use App\Models\Feedback;
use App\Models\User;
use App\Models\VendorListing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $vendorProfiles = BusinessProfile::query()
            ->whereHas('user', fn ($query) => $query->where('role', 'vendor'));

        return view('admin.dashboard', [
            'admin' => $request->user(),
            'stats' => [
                'umkm' => User::query()->where('role', 'umkm')->count(),
                'vendors' => User::query()->where('role', 'vendor')->count(),
                'pending_verifications' => (clone $vendorProfiles)->where('verification_status', 'pending')->count(),
                'feedback' => Feedback::query()->count(),
                'pending_ad_payments' => AdCampaign::query()
                    ->where('payment_status', 'paid')
                    ->whereIn('status', [
                        AdCampaign::STATUS_PAID,
                        AdCampaign::STATUS_ASSET_SUBMITTED,
                    ])
                    ->count(),
                'pending_vendor_listings' => VendorListing::query()
                    ->where('status', VendorListing::STATUS_PENDING)
                    ->count(),
            ],
            'recentVerifications' => (clone $vendorProfiles)
                ->with('user:id,name,email')
                ->where('verification_status', 'pending')
                ->latest('verification_submitted_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
