<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertisementClickController extends Controller
{
    public function __invoke(Request $request, AdCampaign $adCampaign): RedirectResponse|View
    {
        abort_unless($adCampaign->status === AdCampaign::STATUS_ACTIVE
            && $adCampaign->starts_at?->isPast()
            && $adCampaign->ends_at?->isFuture(), 404);

        if ($request->user()?->role === 'umkm') {
            return view('advertisements.vendor-only', ['campaign' => $adCampaign]);
        }

        abort_unless(is_string($adCampaign->destination_url) && $adCampaign->destination_url !== '', 404);

        return redirect()->away($adCampaign->destination_url);
    }
}
