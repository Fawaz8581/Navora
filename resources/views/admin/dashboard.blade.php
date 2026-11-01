@extends('admin.layout')

@section('title', 'Dashboard')
@section('meta-description', 'Pantau aktivitas Navora, tinjau pengajuan vendor, dan kelola masukan pengguna.')

@section('admin-content')
    <div class="admin-dashboard">
        <section class="admin-welcome" aria-labelledby="admin-dashboard-title">
            <div class="admin-welcome-copy">
                <span class="admin-eyebrow"><span class="admin-live-dot"></span> RUANG KERJA ADMIN</span>
                <h1 id="admin-dashboard-title">Selamat datang, {{ $admin->name }}</h1>
                <p>Pusat kendali untuk memantau aktivitas Navora, meninjau vendor, dan menindaklanjuti kebutuhan pengguna.</p>
                <div class="admin-welcome-actions">
                    <a class="admin-dashboard-primary-action" href="{{ route('admin.vendor-verifications.index', ['status' => 'pending']) }}">
                        Tinjau pengajuan <i class="bi bi-arrow-up-right"></i>
                    </a>
                    <a class="admin-dashboard-secondary-action" href="{{ route('admin.feedback.index') }}">
                        Buka masukan <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="admin-welcome-aside" aria-label="Ringkasan tindak lanjut">
                <div class="admin-welcome-orbit admin-welcome-orbit-one"></div>
                <div class="admin-welcome-orbit admin-welcome-orbit-two"></div>
                <div class="admin-welcome-summary">
                    <span class="admin-welcome-summary-icon"><i class="bi bi-activity"></i></span>
                    <span class="admin-welcome-summary-label">PERLU TINDAK LANJUT</span>
                    <strong>{{ number_format($stats['pending_verifications'] + $stats['pending_ad_payments']) }}</strong>
                    <span class="admin-welcome-summary-copy">pengajuan vendor dan pembayaran iklan</span>
                </div>
                <div class="admin-welcome-floating-icon admin-welcome-floating-icon-one"><i class="bi bi-shop"></i></div>
                <div class="admin-welcome-floating-icon admin-welcome-floating-icon-two"><i class="bi bi-megaphone"></i></div>
            </div>
        </section>

        <section class="admin-dashboard-overview" aria-labelledby="admin-overview-title">
            <div class="admin-dashboard-section-heading">
                <div>
                    <span class="admin-eyebrow">SEKILAS NAVORA</span>
                    <h2 id="admin-overview-title">Ringkasan platform</h2>
                </div>
                <span class="admin-dashboard-date"><i class="bi bi-calendar3"></i> {{ now()->format('d.m.Y') }}</span>
            </div>

            <div class="admin-stats-grid" aria-label="Statistik Navora">
                <article class="admin-stat-card admin-stat-users">
                    <div class="admin-stat-card-heading"><span>UMKM terdaftar</span><span class="admin-stat-icon admin-stat-blue"><i class="bi bi-shop"></i></span></div>
                    <strong>{{ number_format($stats['umkm']) }}</strong>
                    <small>Usaha yang bergabung</small>
                </article>
                <article class="admin-stat-card admin-stat-vendors">
                    <div class="admin-stat-card-heading"><span>Vendor terdaftar</span><span class="admin-stat-icon admin-stat-teal"><i class="bi bi-box-seam"></i></span></div>
                    <strong>{{ number_format($stats['vendors']) }}</strong>
                    <small>Penyedia produk dan layanan</small>
                </article>
                <article class="admin-stat-card admin-stat-pending">
                    <div class="admin-stat-card-heading"><span>Menunggu verifikasi</span><span class="admin-stat-icon admin-stat-gold"><i class="bi bi-hourglass-split"></i></span></div>
                    <strong>{{ number_format($stats['pending_verifications']) }}</strong>
                    <small>Pengajuan perlu ditinjau</small>
                </article>
                <article class="admin-stat-card admin-stat-feedback">
                    <div class="admin-stat-card-heading"><span>Total masukan</span><span class="admin-stat-icon admin-stat-purple"><i class="bi bi-chat-square-text"></i></span></div>
                    <strong>{{ number_format($stats['feedback']) }}</strong>
                    <small>Masukan dari pengguna</small>
                </article>
                <article class="admin-stat-card admin-stat-payments">
                    <div class="admin-stat-card-heading"><span>Pembayaran iklan</span><span class="admin-stat-icon admin-stat-gold"><i class="bi bi-megaphone"></i></span></div>
                    <strong>{{ number_format($stats['pending_ad_payments']) }}</strong>
                    <small>Menunggu pemeriksaan</small>
                </article>
            </div>
        </section>

        <section class="admin-dashboard-grid" aria-label="Tindakan dan aktivitas admin">
            <article class="admin-panel admin-recent-panel">
                <div class="admin-panel-heading">
                    <div>
                        <span class="admin-eyebrow">ANTREAN TINJAUAN</span>
                        <h2>Pengajuan verifikasi vendor</h2>
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
                                <tr><th>Usaha</th><th>Pemilik akun</th><th>Tanggal masuk</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($recentVerifications as $profile)
                                    <tr>
                                        <td><strong>{{ $profile->business_name ?: 'Usaha tanpa nama' }}</strong><small>{{ $profile->location ?: 'Lokasi belum diisi' }}</small></td>
                                        <td>{{ $profile->user->name }}</td>
                                        <td>{{ $profile->verification_submitted_at?->format('d M Y') ?: '—' }}</td>
                                        <td><a class="admin-table-action" href="{{ route('admin.vendor-verifications.show', $profile) }}">Tinjau <i class="bi bi-arrow-up-right"></i></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </article>

            <aside class="admin-panel admin-quick-links">
                <div class="admin-quick-links-heading">
                    <span class="admin-eyebrow">NAVIGASI</span>
                    <h2>Akses cepat</h2>
                    <p>Pilih area yang ingin kamu kelola.</p>
                </div>
                <a href="{{ route('admin.vendor-verifications.index') }}">
                    <span class="admin-quick-icon"><i class="bi bi-patch-check"></i></span>
                    <span><strong>Verifikasi vendor</strong><small>Tinjau profil dan dokumen</small></span>
                    @if ($stats['pending_verifications'] > 0)<span class="admin-quick-count">{{ number_format($stats['pending_verifications']) }}</span>@endif
                    <i class="bi bi-arrow-up-right"></i>
                </a>
                <a href="{{ route('admin.feedback.index') }}">
                    <span class="admin-quick-icon admin-quick-icon-purple"><i class="bi bi-chat-square-text"></i></span>
                    <span><strong>Masukan pengguna</strong><small>Baca saran untuk Navora</small></span>
                    <i class="bi bi-arrow-up-right"></i>
                </a>
                <a href="{{ route('admin.customize.index') }}">
                    <span class="admin-quick-icon admin-quick-icon-gold"><i class="bi bi-megaphone"></i></span>
                    <span><strong>Paket dan iklan</strong><small>Kelola paket dan pembayaran</small></span>
                    @if ($stats['pending_ad_payments'] > 0)<span class="admin-quick-count admin-quick-count-gold">{{ number_format($stats['pending_ad_payments']) }}</span>@endif
                    <i class="bi bi-arrow-up-right"></i>
                </a>
            </aside>
        </section>
    </div>
@endsection
