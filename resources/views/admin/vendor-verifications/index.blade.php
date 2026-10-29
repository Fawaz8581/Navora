@extends('auth.partials.layout')

@section('title', 'Pemeriksaan vendor')
@section('meta-description', 'Kelola pemeriksaan dokumen vendor Navora.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> ADMIN NAVORA</span>
        <h2>Pemeriksaan vendor</h2>
        <p>Periksa dokumen dan data vendor sebelum menyetujui profilnya.</p>
    </div>

    <p class="auth-switch"><a href="{{ route('admin.feedback.index') }}">Lihat masukan pengguna</a></p>

    <nav class="onboarding-progress" aria-label="Filter status verifikasi">
        @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Perlu perbaikan', 'all' => 'Semua'] as $value => $label)
            <a class="onboarding-progress-step {{ $status === $value ? 'is-current' : '' }}" href="{{ route('admin.vendor-verifications.index', ['status' => $value]) }}" @if ($status === $value) aria-current="page" @endif>
                <small>{{ $label }}</small>
            </a>
        @endforeach
    </nav>

    @if ($profiles->isEmpty())
        <div class="onboarding-review-section">
            <h3>Belum ada pengajuan</h3>
            <p>Pengajuan verifikasi vendor dengan status ini akan muncul di sini.</p>
        </div>
    @else
        <div class="onboarding-review">
            @foreach ($profiles as $profile)
                <section class="onboarding-review-section">
                    <div class="onboarding-review-heading">
                        <h3>{{ $profile->business_name ?: 'Usaha tanpa nama' }}</h3>
                        <a href="{{ route('admin.vendor-verifications.show', $profile) }}">Periksa</a>
                    </div>
                    <div class="onboarding-review-row"><span>Pemilik akun</span><strong>{{ $profile->user->name }}</strong></div>
                    <div class="onboarding-review-row"><span>Lokasi</span><strong>{{ $profile->location ?: 'Belum diisi' }}</strong></div>
                    <div class="onboarding-review-row"><span>Status</span><strong>{{ ucfirst($profile->verification_status) }}</strong></div>
                    <div class="onboarding-review-row"><span>Dikirim</span><strong>{{ $profile->verification_submitted_at?->format('d M Y, H:i') ?: '—' }}</strong></div>
                </section>
            @endforeach
        </div>
        {{ $profiles->links() }}
    @endif

    <form class="onboarding-logout" action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit">Keluar dari akun admin</button>
    </form>
@endsection
