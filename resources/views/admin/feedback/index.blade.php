@extends('auth.partials.layout')

@section('title', 'Masukan pengguna')
@section('meta-description', 'Masukan pengguna tentang platform Navora dan vendor.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> ADMIN NAVORA</span>
        <h2>Masukan pengguna</h2>
        <p>Masukan dari pengguna tentang platform Navora dan vendor.</p>
    </div>

    @if ($feedback->isEmpty())
        <div class="onboarding-review-section">
            <h3>Belum ada masukan</h3>
            <p>Masukan yang dikirim pengguna akan muncul di sini.</p>
        </div>
    @else
        <div class="onboarding-review">
            @foreach ($feedback as $item)
                <section class="onboarding-review-section">
                    <div class="onboarding-review-heading">
                        <h3>{{ $item->topic === 'vendor' ? 'Masukan tentang vendor' : 'Masukan tentang Navora' }}</h3>
                        <span>{{ $item->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    @if ($item->vendor_name)
                        <div class="onboarding-review-row"><span>Vendor</span><strong>{{ $item->vendor_name }}</strong></div>
                    @endif
                    <div class="onboarding-review-row"><span>Penilaian</span><strong>{{ $item->rating ? $item->rating.' dari 5' : 'Tidak diberikan' }}</strong></div>
                    <div class="onboarding-review-row"><span>Dari</span><strong>{{ $item->user?->name ?? 'Pengunjung' }}</strong></div>
                    <p class="feedback-message">{{ $item->message }}</p>
                </section>
            @endforeach
        </div>
        {{ $feedback->links() }}
    @endif

    <p class="auth-switch"><a href="{{ route('admin.vendor-verifications.index') }}">Kembali ke pemeriksaan vendor</a></p>
    <form class="onboarding-logout" action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit">Keluar dari akun admin</button>
    </form>
@endsection
