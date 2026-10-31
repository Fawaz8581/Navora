@extends('admin.layout')

@section('title', 'Tinjau profil vendor')
@section('meta-description', 'Periksa profil dan dokumen vendor Navora.')

@section('admin-content')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> ADMIN NAVORA</span>
        <h2>{{ $profile->business_name ?: 'Profil vendor' }}</h2>
        <p>Periksa kesesuaian data usaha dan dokumen sebelum mengambil keputusan.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Periksa kembali keputusan dan catatan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="onboarding-review-section">
        <h3>Profil vendor</h3>
        @foreach ([
            'Nama usaha' => $profile->business_name,
            'Nama pemilik akun' => $profile->user->name,
            'Email' => $profile->user->email,
            'Nomor kontak' => $profile->phone,
            'Bidang usaha' => $profile->category,
            'Lokasi' => $profile->location,
            'Tentang usaha' => $profile->description,
            'Jenis kemasan atau layanan' => $profile->product_categories,
            'Bahan' => $profile->materials,
            'MOQ' => $profile->minimum_order_quantity,
            'Kapasitas produksi per bulan' => $profile->monthly_capacity,
            'Waktu produksi (hari)' => $profile->production_lead_time_days,
            'Nomor NIB' => $profile->nib,
            'Status verifikasi' => ucfirst($profile->verification_status),
            'Catatan admin' => $profile->verification_notes,
        ] as $label => $value)
            <div class="onboarding-review-row"><span>{{ $label }}</span><strong>{{ filled($value) ? $value : 'Belum diisi' }}</strong></div>
        @endforeach
    </section>

    @foreach ($documents as $type => $files)
        <section class="onboarding-review-section">
            <h3>{{ ['nib' => 'Dokumen NIB', 'work_samples' => 'Contoh pekerjaan', 'production_photos' => 'Foto tempat produksi', 'certificates' => 'Sertifikat'][$type] }}</h3>
            @forelse ($files as $index => $file)
                <div class="onboarding-review-row">
                    <span>{{ $file['label'] }}</span>
                    <a href="{{ route('admin.vendor-verifications.documents', [$profile, $type, $index]) }}">{{ $file['label'] }} · Unduh</a>
                </div>
            @empty
                <p>Tidak ada dokumen.</p>
            @endforelse
        </section>
    @endforeach

    @if ($profile->verification_status === 'pending')
        <form action="{{ route('admin.vendor-verifications.review', $profile) }}" method="post" class="auth-form">
            @csrf
            <div class="auth-field">
                <label for="verification_notes">Catatan untuk vendor (wajib jika ditolak)</label>
                <textarea class="onboarding-textarea" id="verification_notes" name="verification_notes" rows="4" maxlength="2000">{{ old('verification_notes') }}</textarea>
                @error('verification_notes')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="d-flex flex-column gap-2">
                <button class="auth-submit" type="submit" name="decision" value="approved">Setujui vendor <i class="bi bi-check2"></i></button>
                <button class="auth-submit" type="submit" name="decision" value="rejected">Tolak dan minta perbaikan <i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
        </form>
    @endif

    <a class="onboarding-back-link" href="{{ route('admin.vendor-verifications.index') }}"><i class="bi bi-arrow-left"></i> Kembali ke daftar pemeriksaan</a>
@endsection
