<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorListingController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', VendorListing::STATUS_PENDING);
        abort_unless(in_array($status, [
            VendorListing::STATUS_PENDING,
            VendorListing::STATUS_ACTIVE,
            'all',
        ], true), 404);

        $listings = VendorListing::query()
            ->with(['vendor:id,name,email', 'vendor.businessProfile', 'approver:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.vendor-listings.index', [
            'listings' => $listings,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, VendorListing $vendorListing): RedirectResponse
    {
        abort_unless($vendorListing->status === VendorListing::STATUS_PENDING, 409);

        $profile = $vendorListing->vendor?->businessProfile;
        abort_unless(
            $profile?->completed_at !== null && $profile->verification_status === 'approved',
            409,
            'Profil vendor harus tetap terverifikasi untuk menyetujui katalog.',
        );

        $vendorListing->update([
            'status' => VendorListing::STATUS_ACTIVE,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('admin.vendor-listings.index')
            ->with('status', 'Katalog telah disetujui dan aktif di pencarian vendor.');
    }
}
