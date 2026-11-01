<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\VendorSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;

class VendorVerificationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        abort_unless($request->user()->role === 'vendor', 403);

        $profile = $request->user()->businessProfile;

        if (! $profile?->completed_at) {
            return redirect()->route('onboarding.index');
        }

        return view('vendor.verification', [
            'profile' => $profile,
            'vendorSubscription' => VendorSubscription::query()
                ->where('vendor_user_id', $request->user()->id)
                ->where('payment_status', 'paid')
                ->latest('id')
                ->first(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'vendor', 403);

        $profile = $request->user()->businessProfile;

        if (! $profile?->completed_at) {
            return redirect()->route('onboarding.index');
        }

        if (! filled($profile->business_address)) {
            return redirect()
                ->route('onboarding.step', ['step' => 1])
                ->withErrors(['business_address' => 'Isi alamat lengkap usaha sebelum mengajukan verifikasi vendor.']);
        }

        if (! filled($profile->rajaongkir_area_id) || ! filled($profile->rajaongkir_area_name)) {
            return redirect()
                ->route('onboarding.step', ['step' => 1])
                ->withErrors(['rajaongkir_area_name' => 'Pilih area asal pengiriman vendor sebelum mengajukan verifikasi.']);
        }

        abort_unless(in_array($profile->verification_status, ['not_submitted', 'rejected'], true), 409);

        $validated = $request->validate([
            'nib' => ['required', 'string', 'max:32'],
            'nib_document' => [$profile->nib_document_path ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'work_samples' => [
                empty($profile->work_sample_paths) ? 'required' : 'sometimes',
                'array',
                'min:1',
                'max:5',
            ],
            'work_samples.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'production_photos' => [
                empty($profile->production_photo_paths) ? 'required' : 'sometimes',
                'array',
                'min:1',
                'max:5',
            ],
            'production_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'certificates' => ['sometimes', 'array', 'max:5'],
            'certificates.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'nib.required' => 'Masukkan nomor NIB usaha.',
            'nib_document.required' => 'Unggah dokumen NIB.',
            'work_samples.required' => 'Unggah setidaknya satu contoh pekerjaan.',
            'production_photos.required' => 'Unggah setidaknya satu foto tempat produksi.',
        ]);

        if ($profile->verification_status === 'rejected'
            && ! (
                $request->hasFile('nib_document')
                || $request->hasFile('work_samples')
                || $request->hasFile('production_photos')
                || $request->hasFile('certificates')
            )) {
            return back()
                ->withErrors(['documents' => 'Unggah setidaknya satu dokumen perbaikan sebelum mengirim ulang.'])
                ->withInput();
        }

        $nibDocument = $request->file('nib_document');
        $nibDocumentPath = $nibDocument
            ? $this->storeUpload($nibDocument, 'vendor-verification/'.$profile->id)
            : null;
        $workSamplePaths = $this->storeUploads($request, 'work_samples', $profile);
        $productionPhotoPaths = $this->storeUploads($request, 'production_photos', $profile);
        $certificatePaths = $this->storeUploads($request, 'certificates', $profile);

        $profile->forceFill([
            'nib' => $validated['nib'],
            'nib_document_path' => $nibDocumentPath ?? $profile->nib_document_path,
            'work_sample_paths' => $workSamplePaths !== [] ? $workSamplePaths : ($profile->work_sample_paths ?? []),
            'production_photo_paths' => $productionPhotoPaths !== [] ? $productionPhotoPaths : ($profile->production_photo_paths ?? []),
            'certificate_paths' => $certificatePaths !== [] ? $certificatePaths : ($profile->certificate_paths ?? []),
            'verification_status' => 'pending',
            'verification_notes' => null,
            'verification_submitted_at' => now(),
            'verification_reviewed_at' => null,
            'verified_by' => null,
        ])->save();

        return redirect()
            ->route('vendor.verification.index')
            ->with('status', 'Dokumen verifikasi berhasil dikirim dan sedang menunggu pemeriksaan admin.');
    }

    /**
     * @return list<string>
     */
    private function storeUploads(Request $request, string $field, BusinessProfile $profile): array
    {
        $paths = [];

        foreach ($request->file($field, []) as $file) {
            $paths[] = $this->storeUpload($file, 'vendor-verification/'.$profile->id.'/'.$field);
        }

        return $paths;
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'local');

        if ($path === false) {
            throw new RuntimeException('Dokumen verifikasi tidak berhasil disimpan.');
        }

        return $path;
    }
}
