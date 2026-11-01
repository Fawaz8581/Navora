<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertisementController extends Controller
{
    public function index(): View
    {
        return view('admin.customize', [
            'packages' => AdPackage::query()->orderBy('price')->get(),
            'campaigns' => AdCampaign::query()
                ->with('vendor:id,name,email')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $validated = $this->validatedPackage($request);

        AdPackage::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Paket iklan berhasil ditambahkan.');
    }

    public function updatePackage(Request $request, AdPackage $adPackage): RedirectResponse
    {
        $validated = $this->validatedPackage($request);

        $adPackage->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Pengaturan paket iklan berhasil diperbarui.');
    }

    public function reviewCampaign(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        abort_unless($adCampaign->status === AdCampaign::STATUS_PAID, 409);

        $adCampaign->forceFill([
            'status' => AdCampaign::STATUS_AWAITING_ASSET,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        return back()->with('status', 'Campaign disetujui. Vendor dapat mengunggah aset iklan.');
    }

    public function publishCampaign(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        abort_unless(
            $adCampaign->status === AdCampaign::STATUS_ASSET_SUBMITTED && $adCampaign->asset_path,
            409,
        );

        $startsAt = now();
        $adCampaign->forceFill([
            'status' => AdCampaign::STATUS_ACTIVE,
            'published_by' => $request->user()->id,
            'published_at' => $startsAt,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($adCampaign->duration_days),
        ])->save();

        return back()->with('status', 'Aset iklan dipublikasikan di beranda dan ruang kerja.');
    }

    /**
     * @return array{name: string, placement: string, width: int, height: int, price: int, duration_days: int}
     */
    private function validatedPackage(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'placement' => ['required', 'in:top,sidebar'],
            'width' => ['required', 'integer', 'min:50', 'max:3000'],
            'height' => ['required', 'integer', 'min:50', 'max:3000'],
            'price' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => $validated['name'],
            'placement' => $validated['placement'],
            'width' => (int) $validated['width'],
            'height' => (int) $validated['height'],
            'price' => (int) $validated['price'],
            'duration_days' => (int) $validated['duration_days'],
        ];
    }
}
