<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdvertisementAssetController extends Controller
{
    public function show(AdCampaign $adCampaign): BinaryFileResponse
    {
        abort_unless($adCampaign->status === AdCampaign::STATUS_ACTIVE
            && $adCampaign->starts_at?->isPast()
            && $adCampaign->ends_at?->isFuture(), 404);

        return $this->fileResponse($adCampaign);
    }

    public function showForAdmin(AdCampaign $adCampaign): BinaryFileResponse
    {
        abort_unless(in_array($adCampaign->status, [
            AdCampaign::STATUS_ASSET_SUBMITTED,
            AdCampaign::STATUS_ACTIVE,
        ], true), 404);

        return $this->fileResponse($adCampaign);
    }

    private function fileResponse(AdCampaign $adCampaign): BinaryFileResponse
    {
        abort_unless($adCampaign->asset_path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($adCampaign->asset_path), 404);

        $mimeType = $disk->mimeType($adCampaign->asset_path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true), 404);

        return response()->file($disk->path($adCampaign->asset_path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
