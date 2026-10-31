@extends('auth.partials.layout')

@section('title', 'Verifikasi vendor')
@section('meta-description', 'Kirim dokumen usaha untuk pemeriksaan verifikasi vendor Navora.')

@section('auth-form')
    @php
        $verificationLabels = [
            'not_submitted' => 'Belum diajukan',
            'pending' => 'Menunggu pemeriksaan',
            'approved' => 'Terverifikasi',
            'rejected' => 'Perlu perbaikan',
        ];
    @endphp

    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> VERIFIKASI VENDOR</span>
        <h2>Lengkapi dokumen usahamu</h2>
        <p>Admin Navora akan memeriksa identitas usaha, contoh pekerjaan, dan tempat produksimu.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Periksa kembali dokumen dan informasi berikut.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="onboarding-review-section">
        <h3>Status verifikasi: {{ $verificationLabels[$profile->verification_status] ?? 'Belum diajukan' }}</h3>
        @if ($profile->verification_submitted_at)
            <p>Terakhir dikirim {{ $profile->verification_submitted_at->format('d M Y, H:i') }}.</p>
        @endif
        @if ($profile->verification_notes)
            <div class="auth-alert auth-alert-error" role="status">
                <i class="bi bi-exclamation-circle"></i>
                <div><strong>Alasan penolakan</strong><p>{{ $profile->verification_notes }}</p></div>
            </div>
        @endif
        @if ($profile->verification_status === 'approved')
            <p>Profil vendor telah diperiksa dan disetujui admin.</p>
        @elseif ($profile->verification_status === 'pending')
            <p>Dokumenmu sedang diperiksa. Kamu akan dapat menggunakan ruang kerja setelah verifikasi disetujui.</p>
        @elseif ($profile->verification_status === 'rejected')
            <p>Akun vendor belum terverifikasi. Perbaiki dokumen sesuai alasan di atas, lalu unggah dokumen yang perlu diperbarui untuk mengajukan verifikasi ulang.</p>
        @endif
    </section>

    @if (in_array($profile->verification_status, ['not_submitted', 'rejected'], true))
        <form action="{{ route('vendor.verification.submit') }}" method="post" class="auth-form" enctype="multipart/form-data">
            @csrf
            @error('documents')<div class="auth-alert auth-alert-error" role="alert">{{ $message }}</div>@enderror
            <div class="auth-field">
                <label for="nib">Nomor Induk Berusaha (NIB)</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-card-text" aria-hidden="true"></i>
                    <input id="nib" name="nib" type="text" value="{{ old('nib', $profile->nib) }}" maxlength="32" required>
                </div>
                @error('nib')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="nib_document">Dokumen NIB (PDF/JPG/PNG, maks. 10 MB)</label>
                <input id="nib_document" name="nib_document" type="file" accept=".pdf,.jpg,.jpeg,.png" @required(! $profile->nib_document_path)>
                @if ($profile->nib_document_path)<small>Dokumen sebelumnya tersimpan. Pilih file baru jika ingin menggantinya.</small>@endif
                @error('nib_document')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="work_samples">Contoh pekerjaan (1–5 gambar, maks. 5 MB per file)</label>
                <input id="work_samples" name="work_samples[]" type="file" accept="image/jpeg,image/png,image/webp" multiple @required(empty($profile->work_sample_paths))>
                <small>{{ count($profile->work_sample_paths ?? []) }} contoh pekerjaan sudah diunggah. Jika mengunggah contoh baru, file lama pada bagian ini akan diganti.</small>
                @error('work_samples')<span class="auth-field-error">{{ $message }}</span>@enderror
                @error('work_samples.*')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="production_photos">Foto tempat produksi (1–5 gambar, maks. 5 MB per file)</label>
                <input id="production_photos" name="production_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple @required(empty($profile->production_photo_paths))>
                <small>{{ count($profile->production_photo_paths ?? []) }} foto tempat produksi sudah diunggah. Jika mengunggah foto baru, file lama pada bagian ini akan diganti.</small>
                @error('production_photos')<span class="auth-field-error">{{ $message }}</span>@enderror
                @error('production_photos.*')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="certificates">Sertifikat pendukung (opsional, maks. 5 file)</label>
                <input id="certificates" name="certificates[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple>
                <small>{{ count($profile->certificate_paths ?? []) }} sertifikat sudah diunggah. Jika mengunggah sertifikat baru, file lama pada bagian ini akan diganti.</small>
                @error('certificates')<span class="auth-field-error">{{ $message }}</span>@enderror
                @error('certificates.*')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <p class="auth-privacy-note"><i class="bi bi-shield-lock"></i> Dokumen disimpan secara privat dan hanya dapat diperiksa admin yang berwenang.</p>
            <button class="auth-submit" type="submit">{{ $profile->verification_status === 'rejected' ? 'Kirim ulang verifikasi' : 'Kirim untuk diperiksa' }} <i class="bi bi-send"></i></button>
        </form>
    @elseif ($profile->verification_status === 'approved')
        <a class="auth-submit onboarding-finish-link" href="{{ route('workspace') }}">Kembali ke ruang kerja <i class="bi bi-arrow-right"></i></a>
    @endif

    <a class="onboarding-back-link" href="{{ route('business-profile.show') }}"><i class="bi bi-arrow-left"></i> Kembali ke profil usaha</a>
@endsection
