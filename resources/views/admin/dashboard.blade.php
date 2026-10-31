@extends('admin.layout')

@section('title', 'Dashboard')
@section('meta-description', 'Ringkasan aktivitas dan pengelolaan platform Navora.')

@section('admin-content')
    <section class="admin-welcome">
        <div>
            <span class="admin-eyebrow">RUANG KERJA ADMIN</span>
            <h1>Selamat datang, {{ $admin->name }}</h1>
            <p>Kelola pengguna, pantau verifikasi vendor, dan lihat masukan terbaru di Navora.</p>
        </div>
        <span class="admin-welcome-icon"><i class="bi bi-box-seam"></i></span>
    </section>

    <section class="admin-stats-grid" aria-label="Statistik Navora">
        <article class="admin-stat-card">
            <span class="admin-stat-icon admin-stat-blue"><i class="bi bi-shop"></i></span>
            <div><span>UMKM terdaftar</span><strong>{{ number_format($stats['umkm']) }}</strong></div>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-icon admin-stat-teal"><i class="bi bi-box-seam"></i></span>
            <div><span>Vendor terdaftar</span><strong>{{ number_format($stats['vendors']) }}</strong></div>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-icon admin-stat-gold"><i class="bi bi-hourglass-split"></i></span>
            <div><span>Menunggu verifikasi</span><strong>{{ number_format($stats['pending_verifications']) }}</strong></div>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-icon admin-stat-purple"><i class="bi bi-chat-square-text"></i></span>
            <div><span>Total masukan</span><strong>{{ number_format($stats['feedback']) }}</strong></div>
        </article>
    </section>

    <section class="admin-dashboard-grid">
        <article class="admin-panel admin-recent-panel">
            <div class="admin-panel-heading">
                <div>
                    <span class="admin-eyebrow">TINDAKAN ADMIN</span>
                    <h2>Pengajuan verifikasi terbaru</h2>
                </div>
                <a href="{{ route('admin.vendor-verifications.index', ['status' => 'pending']) }}">Lihat semua <i class="bi bi-arrow-right"></i></a>
            </div>

            @if ($recentVerifications->isEmpty())
                <div class="admin-empty-state">
                    <i class="bi bi-check2-circle"></i>
                    <strong>Tidak ada pengajuan yang menunggu</strong>
                    <span>Pengajuan baru akan muncul di sini.</span>
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Usaha</th><th>Pemilik akun</th><th>Tanggal</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentVerifications as $profile)
                                <tr>
                                    <td><strong>{{ $profile->business_name ?: 'Usaha tanpa nama' }}</strong><small>{{ $profile->location ?: 'Lokasi belum diisi' }}</small></td>
                                    <td>{{ $profile->user->name }}</td>
                                    <td>{{ $profile->verification_submitted_at?->format('d M Y') ?: '—' }}</td>
                                    <td><a class="admin-table-action" href="{{ route('admin.vendor-verifications.show', $profile) }}">Tinjau</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>

        <aside class="admin-panel admin-quick-links">
            <span class="admin-eyebrow">AKSES CEPAT</span>
            <h2>Kelola Navora</h2>
            <a href="{{ route('admin.vendor-verifications.index') }}">
                <span class="admin-quick-icon"><i class="bi bi-patch-check"></i></span>
                <span><strong>Verifikasi vendor</strong><small>Tinjau dan kelola pengajuan</small></span>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="{{ route('admin.feedback.index') }}">
                <span class="admin-quick-icon"><i class="bi bi-chat-square-text"></i></span>
                <span><strong>Masukan pengguna</strong><small>Baca masukan untuk Navora</small></span>
                <i class="bi bi-chevron-right"></i>
            </a>
        </aside>
    </section>
@endsection
