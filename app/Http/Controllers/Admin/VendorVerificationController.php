<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendorVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending', 'approved', 'rejected', 'all'], true), 404);

        $profiles = BusinessProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('role', 'vendor'))
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('verification_status', $status),
                fn ($query) => $query->whereIn('verification_status', ['pending', 'approved', 'rejected']),
            )
            ->latest('verification_submitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.vendor-verifications.index', [
            'profiles' => $profiles,
            'status' => $status,
        ]);
    }

    public function show(BusinessProfile $businessProfile): View
    {
        abort_unless($businessProfile->user?->role === 'vendor', 404);

        return view('admin.vendor-verifications.show', [
            'profile' => $businessProfile->load('user'),
            'documents' => $this->documentsFor($businessProfile),
        ]);
    }

    public function review(Request $request, BusinessProfile $businessProfile): RedirectResponse
    {
        abort_unless($businessProfile->user?->role === 'vendor', 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'verification_notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ], [
            'verification_notes.required_if' => 'Berikan alasan jika pengajuan ditolak.',
        ]);

        abort_unless($businessProfile->verification_status === 'pending', 409);

        $businessProfile->forceFill([
            'verification_status' => $validated['decision'],
            'verification_notes' => $validated['verification_notes'] ?? null,
            'verification_reviewed_at' => now(),
            'verified_by' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.vendor-verifications.show', $businessProfile)
            ->with('status', $validated['decision'] === 'approved'
                ? 'Profil vendor telah disetujui.'
                : 'Pengajuan ditolak dan catatan perbaikan telah dikirim.');
    }

    public function download(BusinessProfile $businessProfile, string $type, int $index): StreamedResponse
    {
        abort_unless($businessProfile->user?->role === 'vendor', 404);

        $documents = $this->documentsFor($businessProfile);
        abort_unless(isset($documents[$type][$index]), 404);

        $path = $documents[$type][$index]['path'];
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * @return array<string, list<array{label: string, path: string}>>
     */
    private function documentsFor(BusinessProfile $profile): array
    {
        $documents = [
            'nib' => [],
            'work_samples' => [],
            'production_photos' => [],
            'certificates' => [],
        ];

        if ($profile->nib_document_path) {
            $documents['nib'][] = [
                'label' => 'Dokumen NIB',
                'path' => $profile->nib_document_path,
            ];
        }

        foreach ([
            'work_samples' => ['paths' => $profile->work_sample_paths ?? [], 'label' => 'Contoh pekerjaan'],
            'production_photos' => ['paths' => $profile->production_photo_paths ?? [], 'label' => 'Foto tempat produksi'],
            'certificates' => ['paths' => $profile->certificate_paths ?? [], 'label' => 'Sertifikat'],
        ] as $type => $documentGroup) {
            foreach ($documentGroup['paths'] as $index => $path) {
                $documents[$type][] = [
                    'label' => $documentGroup['label'].' '.($index + 1),
                    'path' => $path,
                ];
            }
        }

        return $documents;
    }
}
