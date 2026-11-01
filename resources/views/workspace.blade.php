<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Demo marketplace Navora untuk mencari vendor kemasan, membandingkan penawaran, dan bergabung dalam patungan MOQ.">
    <title>Ruang Navora — Marketplace kemasan UMKM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/navora-app.css').'?v='.filemtime(public_path('css/navora-app.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/advertisements.css') }}" rel="stylesheet">
</head>
<body class="navora-app-body"
    data-auth-role="{{ auth()->user()->role ?? '' }}"
    data-auth-name="{{ auth()->user()->name ?? '' }}"
    data-auth-user-id="{{ auth()->id() ?? '' }}"
    data-vendor-catalog-url="{{ route('vendors.catalog') }}"
    data-vendor-listings-url="{{ route('vendor.listings.index') }}"
    data-vendor-listings-store-url="{{ route('vendor.listings.store') }}"
    data-orders-url="{{ route('orders.index') }}"
    data-orders-store-url="{{ route('orders.store') }}"
    data-orders-quote-url="{{ route('orders.quote') }}"
    data-orders-show-template="{{ route('orders.show', ['order' => '__ORDER__']) }}"
    data-orders-join-template="{{ route('orders.join', ['order' => '__ORDER__']) }}"
    data-orders-status-template="{{ route('orders.status', ['order' => '__ORDER__']) }}"
    data-orders-price-template="{{ route('orders.price', ['order' => '__ORDER__']) }}"
    data-orders-checkout-template="{{ route('orders.checkout', ['order' => '__ORDER__']) }}">
    <a class="navora-skip-link" href="#app-content">Lewati ke konten utama</a>
    <div class="navora-app-shell">
        <header class="navora-app-header">
            <div class="navora-app-top container-fluid">
                <a class="navora-app-brand" href="{{ route('home') }}">
                    <span class="navora-app-logo"><i class="bi bi-box-seam"></i></span>
                    <span>Navora<span class="navora-brand-dot">.</span><small>MARKETPLACE UMKM</small></span>
                </a>
                <div class="navora-header-search">
                    <i class="bi bi-search"></i>
                    <input type="search" data-global-search placeholder="Cari kemasan, vendor, bahan..." aria-label="Cari di Navora">
                    <kbd>/</kbd>
                </div>
                <div class="navora-header-actions">
                    @auth
                        <span class="navora-demo-label"><i class="bi bi-circle-fill"></i> AKUN TERHUBUNG</span>
                    @else
                        <span class="navora-demo-label"><i class="bi bi-circle-fill"></i> MODE DEMO</span>
                    @endauth
                    <button class="navora-icon-button" type="button" aria-label="Notifikasi demo"><i class="bi bi-bell"></i><span></span></button>
                    @auth
                        <span class="navora-auth-user" title="{{ Auth::user()->email }}">{{ Auth::user()->name }}</span>
                        <form class="navora-logout-form" action="{{ route('logout') }}" method="post">
                            @csrf
                            <button class="navora-logout-button" type="submit" aria-label="Keluar dari akun"><i class="bi bi-box-arrow-right"></i></button>
                        </form>
                    @else
                        <a class="navora-guest-login" href="{{ route('login') }}">Masuk</a>
                    @endauth
                    @auth
                        <span class="navora-account-role">
                            @if (Auth::user()->role === 'vendor')
                                Vendor
                            @elseif (Auth::user()->role === 'partner')
                                Mitra
                            @else
                                UMKM
                            @endif
                        </span>
                    @else
                        <label class="visually-hidden" for="navora-role">Pilih peran demo</label>
                        <select class="navora-role-select" id="navora-role" data-role-select aria-label="Pilih peran demo">
                            <option value="umkm">UMKM</option>
                            <option value="vendor">Vendor</option>
                            <option value="partner">Mitra</option>
                        </select>
                    @endauth
                </div>
            </div>
            <nav class="navora-app-nav" aria-label="Navigasi ruang Navora">
                <div class="container-fluid navora-app-nav-inner">
                    <div class="navora-nav-context"><span class="navora-context-dot"></span><span data-role-caption>Ruang UMKM</span></div>
                    <div class="navora-page-links" role="tablist" aria-label="Halaman demo">
                        <button class="navora-page-link is-active" type="button" data-page="overview" data-roles="umkm,vendor,partner" role="tab" aria-selected="true"><i class="bi bi-grid-1x2"></i> Ringkasan</button>
                        <button class="navora-page-link" type="button" data-page="vendors" data-roles="umkm" role="tab" aria-selected="false"><i class="bi bi-search"></i> Cari vendor</button>
                        <button class="navora-page-link" type="button" data-page="listings" data-roles="vendor" role="tab" aria-selected="false"><i class="bi bi-box-seam"></i> Katalog saya</button>
                        @auth
                            @if (Auth::user()->role === 'vendor')
                                <a class="navora-page-link" href="{{ route('vendor.advertisements.index') }}"><i class="bi bi-megaphone"></i> Iklan saya</a>
                            @endif
                        @endauth
                        <button class="navora-page-link" type="button" data-page="groups" data-roles="umkm,vendor" role="tab" aria-selected="false"><i class="bi bi-people"></i> Patungan MOQ</button>
                        <button class="navora-page-link" type="button" data-page="orders" data-roles="umkm,vendor" role="tab" aria-selected="false"><i class="bi bi-box-seam"></i> Pesanan</button>
                        <button class="navora-page-link" type="button" data-page="rfq" data-roles="umkm,vendor" role="tab" aria-selected="false"><i class="bi bi-chat-square-text"></i> Permintaan penawaran</button>
                        <button class="navora-page-link" type="button" data-page="assistant" data-roles="umkm" role="tab" aria-selected="false"><i class="bi bi-stars"></i> Asisten kemasan</button>
                        <button class="navora-page-link" type="button" data-page="programs" data-roles="partner" role="tab" aria-selected="false"><i class="bi bi-buildings"></i> Program mitra</button>
                        <button class="navora-page-link" type="button" data-page="account" data-roles="umkm,vendor,partner" role="tab" aria-selected="false"><i class="bi bi-person"></i> @auth Profil usaha @else Profil demo @endauth</button>
                    </div>
                    <a class="navora-back-home" href="{{ route('feedback.create') }}"><i class="bi bi-chat-square-heart"></i><span>Kirim masukan</span></a>
                    <a class="navora-back-home" href="{{ route('home') }}"><i class="bi bi-arrow-left"></i><span>Beranda</span></a>
                </div>
            </nav>
        </header>

        <main class="navora-app-main container-fluid" id="app-content">
            @include('advertisements.banner')
            @if (Auth::check() && Auth::user()->role === 'vendor' && $advertisementNotices->isNotEmpty())
                <section class="ad-dashboard-notices" aria-label="Notifikasi keputusan iklan">
                    <h2>Notifikasi iklan</h2>
                    @foreach ($advertisementNotices as $notice)
                        <article class="ad-campaign-review-notice" role="status">
                            <strong>{{ $notice->status === 'active' ? 'Iklan diterima dan ditayangkan' : 'Iklan ditolak admin' }}: {{ $notice->ad_title ?: $notice->package_name }}</strong>
                            @if ($notice->review_reason)
                                <span>Catatan admin: {{ $notice->review_reason }}</span>
                            @endif
                            @if ($notice->status === 'rejected')
                                <span>Status refund: {{ match ($notice->refund_status) {
                                    'requested' => 'Permintaan refund diterima Midtrans',
                                    'refunded' => 'Refund selesai',
                                    'partially_refunded' => 'Refund sebagian',
                                    'cancel_requested' => 'Pembatalan transaksi diterima Midtrans',
                                    'cancelled' => 'Pembayaran berhasil dibatalkan',
                                    'failed' => 'Refund gagal diajukan; admin akan mencoba kembali',
                                    default => 'Sedang diproses',
                                } }}</span>
                            @endif
                        </article>
                    @endforeach
                    <a href="{{ route('vendor.advertisements.index') }}">Lihat bukti pembayaran dan status iklan <i class="bi bi-arrow-right"></i></a>
                </section>
            @endif
            <div class="navora-app-content-layout">
                <div class="navora-app-panels">
            <section class="navora-page-panel is-active" data-page-panel="overview" role="tabpanel">
                <div class="navora-welcome-row">
                    <div>
                        <div class="navora-breadcrumb"><span>Navora</span><i class="bi bi-chevron-right"></i><span data-breadcrumb-current>Ringkasan</span></div>
                        <p class="navora-date-line"><i class="bi bi-sun"></i> Ruang kolaborasi untuk pertumbuhan bisnismu</p>
                        <h1 data-welcome-title>Selamat datang, pemilik brand!</h1>
                        <p class="navora-welcome-copy" data-welcome-copy>Temukan vendor kemasan yang sesuai dan lihat peluang pemesanan bersama.</p>
                    </div>
                    <a class="btn navora-app-primary" href="#vendor-section" data-primary-action><i class="bi bi-search"></i> Jelajahi vendor</a>
                </div>

                <div class="navora-overview-stats" data-role-content="umkm">
                    <article><span class="navora-stat-icon stat-blue"><i class="bi bi-box-seam"></i></span><div><span>Vendor demo</span><strong>12</strong><small>Contoh katalog</small></div></article>
                    <article><span class="navora-stat-icon stat-teal"><i class="bi bi-people"></i></span><div><span>Patungan menunggu peserta</span><strong data-stat-groups>0</strong><small>Pesanan nyata di akun</small></div></article>
                    <article><span class="navora-stat-icon stat-gold"><i class="bi bi-chat-square-text"></i></span><div><span>Permintaan saya</span><strong data-stat-rfqs>2</strong><small>Contoh untuk perbandingan</small></div></article>
                    <article><span class="navora-stat-icon stat-lilac"><i class="bi bi-lightbulb"></i></span><div><span>Ide kemasan</span><strong>Siap</strong><small>Asisten berbasis demo</small></div></article>
                </div>
                <div class="navora-overview-stats" data-role-content="vendor" hidden>
                    <article><span class="navora-stat-icon stat-blue"><i class="bi bi-box-seam"></i></span><div><span>Katalog saya</span><strong data-stat-listings>0</strong><small>Produk ditampilkan</small></div></article>
                    <article><span class="navora-stat-icon stat-teal"><i class="bi bi-chat-square-text"></i></span><div><span>RFQ masuk</span><strong data-stat-incoming>2</strong><small>Contoh permintaan</small></div></article>
                    <article><span class="navora-stat-icon stat-gold"><i class="bi bi-people"></i></span><div><span>Patungan relevan</span><strong>2</strong><small>Peluang kolaborasi</small></div></article>
                    <article><span class="navora-stat-icon stat-lilac"><i class="bi bi-patch-check"></i></span><div><span>Status profil</span><strong data-verification-summary>Draft</strong><small>Verifikasi belum nyata</small></div></article>
                </div>
                <div class="navora-overview-stats" data-role-content="partner" hidden>
                    <article><span class="navora-stat-icon stat-blue"><i class="bi bi-shop-window"></i></span><div><span>Usaha dalam demo</span><strong>24</strong><small>Data contoh</small></div></article>
                    <article><span class="navora-stat-icon stat-teal"><i class="bi bi-boxes"></i></span><div><span>Vendor terdaftar</span><strong>12</strong><small>Contoh katalog</small></div></article>
                    <article><span class="navora-stat-icon stat-gold"><i class="bi bi-calendar-event"></i></span><div><span>Program aktif</span><strong>3</strong><small>Contoh pendampingan</small></div></article>
                    <article><span class="navora-stat-icon stat-lilac"><i class="bi bi-graph-up-arrow"></i></span><div><span>Kolaborasi</span><strong>8</strong><small>Angka simulasi</small></div></article>
                </div>

                <div class="navora-overview-grid">
                    <section class="navora-panel navora-discover-panel" id="vendor-section" data-role-content="umkm">
                        <div class="navora-panel-heading"><div><span class="navora-panel-kicker">DIREKOMENDASIKAN</span><h2>Vendor untuk dijelajahi</h2></div><button class="navora-text-action" type="button" data-open-page="vendors">Lihat semua <i class="bi bi-arrow-right"></i></button></div>
                        <div class="navora-mini-vendor-list" data-mini-vendor-list></div>
                    </section>
                    <section class="navora-panel navora-discover-panel" data-role-content="vendor" hidden>
                        <div class="navora-panel-heading"><div><span class="navora-panel-kicker">AKTIVITAS VENDOR</span><h2>Peluang untuk bisnismu</h2></div><button class="navora-text-action" type="button" data-open-page="rfq">Lihat permintaan <i class="bi bi-arrow-right"></i></button></div>
                        <div class="navora-vendor-opportunity"><span><i class="bi bi-chat-square-heart"></i></span><div><strong>Brand kopi mencari pouch</strong><p>Butuh pouch kopi dengan opsi cetak dan sampel awal.</p><small>Target MOQ 500 pcs · Bandung</small></div><button type="button" class="btn navora-app-outline" data-action="respond-rfq" data-rfq="rfq-demo-1">Tinjau</button></div>
                        <div class="navora-vendor-opportunity"><span><i class="bi bi-people"></i></span><div><strong>Contoh patungan (demo)</strong><p>Jelajahi pesanan bersama yang dibuat oleh pengguna.</p><small>Data contoh tidak termasuk dalam jumlah pesanan nyata.</small></div><button type="button" class="btn navora-app-outline" data-open-page="orders">Lihat pesanan</button></div>
                    </section>
                    <section class="navora-panel navora-discover-panel" data-role-content="partner" hidden>
                        <div class="navora-panel-heading"><div><span class="navora-panel-kicker">EKOSISTEM</span><h2>Peluang dampak bersama</h2></div><button class="navora-text-action" type="button" data-open-page="programs">Lihat program <i class="bi bi-arrow-right"></i></button></div>
                        <div class="navora-partner-highlight"><span><i class="bi bi-mortarboard"></i></span><div><strong>Klinik kemasan untuk UMKM</strong><p>Program contoh untuk membantu brand meninjau kesiapan kemasan dan memahami pilihan vendor.</p><small>Simulasi program · Tidak ada pendaftaran eksternal</small></div></div>
                    </section>
                    <aside class="navora-panel navora-aside-panel" data-role-content="umkm">
                        <div class="navora-panel-heading"><div><span class="navora-panel-kicker">LANGKAH BERIKUTNYA</span><h2>Mulai dari sini</h2></div></div>
                        <button class="navora-checklist-item" type="button" data-open-page="vendors"><span class="navora-check-icon"><i class="bi bi-search"></i></span><span><strong>Cari vendor yang cocok</strong><small>Filter berdasarkan MOQ dan bahan</small></span><i class="bi bi-chevron-right"></i></button>
                        <button class="navora-checklist-item" type="button" data-open-page="rfq"><span class="navora-check-icon"><i class="bi bi-send"></i></span><span><strong>Minta penawaran</strong><small>Bandingkan kebutuhan dengan vendor</small></span><i class="bi bi-chevron-right"></i></button>
                        <button class="navora-checklist-item" type="button" data-open-page="groups"><span class="navora-check-icon"><i class="bi bi-people"></i></span><span><strong>Lihat patungan MOQ</strong><small>Temukan peluang pesan bersama</small></span><i class="bi bi-chevron-right"></i></button>
                    </aside>
                </div>
                <div class="navora-note"><i class="bi bi-info-circle"></i><span>Ini adalah prototipe interaktif. Katalog, profil, program, harga, dan permintaan pada halaman ini merupakan data simulasi, bukan penawaran atau transaksi nyata.</span></div>
            </section>

            <section class="navora-page-panel" data-page-panel="vendors" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Marketplace</span><i class="bi bi-chevron-right"></i><span>Cari vendor</span></div><h1>Temukan partner kemasan</h1><p>Bandingkan pilihan vendor berdasarkan kebutuhan produk dan kapasitas pesanan.</p></div><button class="btn navora-app-outline" type="button" data-open-page="assistant"><i class="bi bi-stars"></i> Minta ide kemasan</button></div>
                <div class="navora-vendor-layout">
                    <aside class="navora-panel navora-filter-panel">
                        <div class="navora-panel-heading"><h2><i class="bi bi-sliders me-2"></i>Filter vendor</h2><button class="navora-text-action" type="button" data-reset-filters>Reset</button></div>
                        <label class="navora-form-label" for="filter-search">Kata kunci</label><div class="navora-input-icon"><i class="bi bi-search"></i><input id="filter-search" type="search" placeholder="Cari produk atau vendor" data-vendor-filter="search"></div>
                        <label class="navora-form-label" for="filter-category">Jenis kemasan atau layanan</label><select class="form-select navora-form-control" id="filter-category" data-vendor-filter="category"><option value="">Semua jenis</option><option value="pouch">Pouch</option><option value="botol">Botol</option><option value="box">Box / karton</option><option value="maklon">Maklon</option></select>
                        <label class="navora-form-label" for="filter-moq">MOQ maksimum</label><select class="form-select navora-form-control" id="filter-moq" data-vendor-filter="moq"><option value="">Semua MOQ</option><option value="500">≤ 500 pcs</option><option value="1000">≤ 1.000 pcs</option><option value="5000">≤ 5.000 pcs</option></select>
                        <label class="navora-form-label" for="filter-material">Bahan</label><select class="form-select navora-form-control" id="filter-material" data-vendor-filter="material"><option value="">Semua bahan</option><option value="kraft">Kraft paper</option><option value="pet">PET</option><option value="pouch">Laminasi pouch</option><option value="carton">Karton</option></select>
                        <label class="navora-form-label" for="filter-location">Lokasi</label><select class="form-select navora-form-control" id="filter-location" data-vendor-filter="location"><option value="">Semua lokasi</option><option value="bandung">Bandung</option><option value="jakarta">Jakarta</option><option value="surabaya">Surabaya</option><option value="yogyakarta">Yogyakarta</option></select>
                        <label class="navora-form-label" for="filter-price">Harga indikatif maksimal / pcs</label><input class="form-control navora-form-control" id="filter-price" type="number" min="1" step="100" placeholder="Opsional" data-vendor-filter="price">
                        <label class="navora-checkbox"><input type="checkbox" data-vendor-filter="sample"><span>Tersedia opsi sampel</span></label>
                        <label class="navora-checkbox"><input type="checkbox" data-vendor-filter="certificate"><span>Memiliki sertifikat</span></label>
                        <label class="navora-form-label" for="filter-distance">Radius jarak (km)</label><select class="form-select navora-form-control" id="filter-distance" data-vendor-filter="distance"><option value="">Tidak dibatasi</option><option value="25">≤ 25 km</option><option value="50">≤ 50 km</option><option value="100">≤ 100 km</option><option value="250">≤ 250 km</option></select>
                        <button class="btn navora-app-outline navora-location-button" type="button" data-action="use-current-location"><i class="bi bi-crosshair"></i> Gunakan lokasi saya</button>
                        <p class="navora-filter-footnote" data-catalog-note>Harga dan jarak hanya ditampilkan jika profil vendor menyediakan data harga dan koordinat.</p>
                    </aside>
                    <div class="navora-vendor-results">
                        <div class="navora-results-heading"><span><strong data-vendor-result-count>0</strong> pilihan untuk dieksplorasi</span><label for="vendor-sort" class="visually-hidden">Urutkan vendor</label><select id="vendor-sort" class="form-select navora-sort-select" data-vendor-sort><option value="recommended">Rekomendasi</option><option value="price">Harga indikatif terendah</option><option value="moq">MOQ terkecil</option><option value="distance">Jarak terdekat</option></select></div>
                        <div class="navora-empty-state" data-vendor-loading hidden><i class="bi bi-arrow-repeat"></i><strong>Memuat vendor</strong><span>Memeriksa katalog vendor terverifikasi.</span></div>
                        <div class="navora-order-error" data-vendor-error role="alert" hidden></div>
                        <div class="navora-vendor-grid" data-vendor-grid>
                            @guest
                            <article class="navora-vendor-card" data-vendor-card data-id="pouchkita" data-name="PouchKita Studio" data-category="packaging" data-product="Standing pouch kopi" data-moq="500" data-material="pouch kraft" data-location="bandung" data-price="1250" data-sample="true" data-verified="true" data-tags="pouch kopi standing pouch cetak">
                                <div class="navora-vendor-art art-pouch"><img src="{{ asset('images/products/coffee.jpg') }}" alt="Kemasan pouch kopi" loading="lazy"><small>FOTO REFERENSI</small><span class="navora-verified-tag"><i class="bi bi-patch-check-fill"></i> Profil demo</span></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>PouchKita Studio</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Bandung</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,9 <small>(demo)</small></span></div><p>Standing pouch custom untuk kopi, snack, dan produk kering.</p><div class="navora-vendor-tags"><span>Pouch laminasi</span><span>Kraft</span><span>Sampel tersedia</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>500 pcs</strong></span><span>Harga indikatif<strong>Rp1.250 / pcs</strong></span><span>Estimasi produksi<strong>14–21 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="PouchKita Studio">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="PouchKita Studio">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="kemasprima" data-name="Kemas Prima" data-category="packaging" data-product="Botol PET" data-moq="1000" data-material="pet" data-location="jakarta" data-price="850" data-sample="true" data-verified="true" data-tags="botol pet minuman kosmetik">
                                <div class="navora-vendor-art art-bottle"><img src="{{ asset('images/products/bottle.jpg') }}" alt="Botol minum" loading="lazy"><small>FOTO REFERENSI</small><span class="navora-verified-tag"><i class="bi bi-patch-check-fill"></i> Profil demo</span></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Kemas Prima</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Jakarta</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,8 <small>(demo)</small></span></div><p>Botol PET dan tutup untuk minuman serta perawatan tubuh.</p><div class="navora-vendor-tags"><span>PET</span><span>Botol</span><span>Sampel tersedia</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>1.000 pcs</strong></span><span>Harga indikatif<strong>Rp850 / pcs</strong></span><span>Estimasi produksi<strong>10–18 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Kemas Prima">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Kemas Prima">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="cetakbumi" data-name="Cetak Bumi" data-category="printing" data-product="Box karton kraft" data-moq="300" data-material="kraft carton" data-location="yogyakarta" data-price="2100" data-sample="false" data-verified="false" data-tags="cetak box karton kraft paper">
                                <div class="navora-vendor-art art-box"><img src="{{ asset('images/products/cardboard.jpg') }}" alt="Kotak karton di gudang pengemasan" loading="lazy"><small>FOTO REFERENSI</small></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Cetak Bumi</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Yogyakarta</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,7 <small>(demo)</small></span></div><p>Cetak digital kemasan kertas dan box untuk batch terbatas.</p><div class="navora-vendor-tags"><span>Kraft paper</span><span>Karton</span><span>Batch kecil</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>300 pcs</strong></span><span>Harga indikatif<strong>Rp2.100 / pcs</strong></span><span>Estimasi produksi<strong>7–14 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Cetak Bumi">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Cetak Bumi">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="rasa-racik" data-name="Rasa Racik Maklon" data-category="maklon" data-product="Maklon minuman serbuk" data-moq="5000" data-material="carton" data-location="surabaya" data-price="3200" data-sample="true" data-verified="false" data-tags="maklon minuman serbuk produksi">
                                <div class="navora-vendor-art art-maklon"><img src="{{ asset('images/products/coffee.jpg') }}" alt="Produk kopi dalam kemasan" loading="lazy"><small>FOTO REFERENSI</small></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Rasa Racik Maklon</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Surabaya</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,8 <small>(demo)</small></span></div><p>Jasa maklon minuman serbuk dengan diskusi formula awal.</p><div class="navora-vendor-tags"><span>Maklon</span><span>Minuman serbuk</span><span>Konsultasi</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>5.000 pcs</strong></span><span>Harga indikatif<strong>Rp3.200 / pcs</strong></span><span>Estimasi produksi<strong>21–35 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Rasa Racik Maklon">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Rasa Racik Maklon">Minta penawaran</button></div></div>
                            </article>
                            @endguest
                        </div>
                        <div class="navora-empty-state" data-vendor-empty hidden><i class="bi bi-search"></i><strong>Belum ada vendor yang cocok</strong><span>Coba ubah kata kunci atau longgarkan filternya.</span></div>
                    </div>
                </div>
                <div class="navora-note" data-vendor-demo-note><i class="bi bi-info-circle"></i><span>Profil katalog pada akun tamu adalah contoh demo, bukan penawaran vendor nyata. Akun masuk hanya melihat vendor yang sudah terverifikasi.</span></div>
            </section>

            <section class="navora-page-panel" data-page-panel="groups" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Kolaborasi</span><i class="bi bi-chevron-right"></i><span>Patungan MOQ</span></div><h1>Pesan bersama, mulai lebih ringan</h1><p>Jelajahi contoh kebutuhan yang bisa digabungkan untuk memenuhi jumlah minimum vendor.</p></div><button class="btn navora-app-primary" type="button" data-action="create-group"><i class="bi bi-plus-lg"></i> Buat patungan</button></div>
                <div class="navora-group-callout"><span><i class="bi bi-info-circle"></i></span><div><strong>Pesanan patungan tersimpan di akun</strong><p>Buat kebutuhan kemasan standar atau gabung ke pesanan yang masih menerima peserta. Jumlah dan status hanya berubah setelah server menyimpan pembaruan.</p></div></div>
                <div class="navora-empty-state"><i class="bi bi-people"></i><strong>Lihat pesanan patungan yang tersedia</strong><span>Daftar peserta, target jumlah, dan status pesanan tersedia di halaman Pesanan.</span><button class="btn navora-app-primary" type="button" data-open-page="orders">Buka pesanan <i class="bi bi-arrow-right"></i></button></div>
            </section>

            <section class="navora-page-panel" data-page-panel="orders" role="tabpanel" hidden>
                <div class="navora-page-title-row">
                    <div><div class="navora-breadcrumb"><span>Kolaborasi</span><i class="bi bi-chevron-right"></i><span>Pesanan</span></div><h1>Pesanan dan pelacakan</h1><p>Kelola pesanan langsung atau patungan dan pantau setiap pembaruan status.</p></div>
                    <div class="navora-order-create-actions" data-order-create-actions>
                        <button class="btn navora-app-outline" type="button" data-action="create-order" data-order-type="langsung"><i class="bi bi-plus-lg"></i> Pesanan langsung</button>
                        <button class="btn navora-app-primary" type="button" data-action="create-order" data-order-type="patungan"><i class="bi bi-people"></i> Buat patungan</button>
                    </div>
                </div>
                <div class="navora-group-callout"><span><i class="bi bi-info-circle"></i></span><div><strong>Alur pembayaran dan status pesanan</strong><p>Vendor menetapkan harga final sebelum pembeli membayar lewat Midtrans. Pesanan langsung dapat diproses setelah lunas; pesanan patungan menunggu target terpenuhi dan setiap peserta membayar porsinya. Setelah pembayaran diterima, vendor memperbarui tahap pesanan secara nyata. Pesanan lama yang berlabel simulasi tetap bergerak otomatis dan bukan konfirmasi produksi, kurir, atau penerimaan.</p></div></div>
                <div class="navora-empty-state" data-orders-loading aria-live="polite"><i class="bi bi-arrow-repeat"></i><strong>Memuat pesanan</strong><span>Data diambil dari akunmu.</span></div>
                <div class="navora-empty-state" data-orders-guest hidden><i class="bi bi-person-lock"></i><strong>Masuk untuk melihat pesanan</strong><span>Pesanan dan pelacakan hanya tersedia untuk akun yang terdaftar.</span><a class="btn navora-app-primary" href="{{ route('login') }}">Masuk ke Navora</a></div>
                <div class="navora-order-error" data-orders-error role="alert" hidden></div>
                <div class="navora-empty-state" data-orders-empty hidden><i class="bi bi-box-seam"></i><strong>Belum ada pesanan</strong><span>Buat pesanan langsung atau mulai pesanan patungan untuk produk kemasan standar.</span></div>
                <div class="navora-order-list" data-orders-list aria-live="polite"></div>
                <button class="btn navora-app-outline navora-order-more" type="button" data-action="load-more-orders" data-orders-more hidden>Muat pesanan lainnya</button>
            </section>

            <section class="navora-page-panel" data-page-panel="rfq" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Kolaborasi</span><i class="bi bi-chevron-right"></i><span>Permintaan penawaran</span></div><h1 data-rfq-heading>Bandingkan penawaran vendor</h1><p data-rfq-description>Kirim kebutuhanmu dan tinjau respons vendor dalam satu ruang.</p></div><button class="btn navora-app-primary" type="button" data-action="create-rfq"><i class="bi bi-plus-lg"></i> Buat permintaan</button></div>
                <div class="navora-rfq-summary"><div><span class="navora-rfq-summary-icon"><i class="bi bi-send"></i></span><span><strong data-rfq-count>2</strong><small>Permintaan demo</small></span></div><div><span class="navora-rfq-summary-icon summary-green"><i class="bi bi-chat-left-dots"></i></span><span><strong data-rfq-quotes>3</strong><small>Respons contoh</small></span></div><div class="navora-rfq-summary-note"><i class="bi bi-info-circle"></i> RFQ demo tidak dikirim kepada vendor nyata.</div></div>
                <div class="navora-rfq-list" data-rfq-list>
                    <article class="navora-rfq-card" data-rfq-card data-id="rfq-demo-1" data-title="Standing pouch kopi 200 g" data-category="Kemasan fleksibel" data-quantity="1.000 pcs" data-status="2 respons">
                        <div class="navora-rfq-card-head"><div class="navora-rfq-icon"><i class="bi bi-bag"></i></div><div><h2>Standing pouch kopi 200 g</h2><span>RFQ-NV-2026-001 · Dibuat 2 hari lalu</span></div><span class="navora-status-pill status-open">2 respons</span></div>
                        <div class="navora-rfq-details"><span><small>Jenis</small><strong>Kemasan fleksibel</strong></span><span><small>Jumlah target</small><strong>1.000 pcs</strong></span><span><small>Material</small><strong>Kraft + laminasi</strong></span><span><small>Tujuan</small><strong>Bandung</strong></span></div>
                        <div class="navora-quote-row"><div class="navora-quote-vendor"><span class="navora-vendor-avatar">PK</span><span><strong>PouchKita Studio</strong><small>MOQ sesuai · indikatif Rp1.250/pcs</small></span></div><button class="btn navora-app-outline" type="button" data-action="quote-detail" data-rfq="rfq-demo-1" data-vendor="PouchKita Studio">Lihat respons</button></div>
                        <div class="navora-quote-row"><div class="navora-quote-vendor"><span class="navora-vendor-avatar avatar-blue">KX</span><span><strong>Kemasan Xpress</strong><small>MOQ sesuai · indikatif Rp1.400/pcs</small></span></div><button class="btn navora-app-outline" type="button" data-action="quote-detail" data-rfq="rfq-demo-1" data-vendor="Kemasan Xpress">Lihat respons</button></div>
                    </article>
                    <article class="navora-rfq-card" data-rfq-card data-id="rfq-demo-2" data-title="Label botol minuman" data-category="Label & percetakan" data-quantity="2.500 pcs" data-status="Menunggu">
                        <div class="navora-rfq-card-head"><div class="navora-rfq-icon icon-lilac"><i class="bi bi-tags"></i></div><div><h2>Label botol minuman</h2><span>RFQ-NV-2026-002 · Dibuat 5 hari lalu</span></div><span class="navora-status-pill status-waiting">Menunggu respons</span></div>
                        <div class="navora-rfq-details"><span><small>Jenis</small><strong>Label & percetakan</strong></span><span><small>Jumlah target</small><strong>2.500 pcs</strong></span><span><small>Material</small><strong>Label tahan air</strong></span><span><small>Tujuan</small><strong>Jakarta</strong></span></div>
                    </article>
                </div>
                <div class="navora-vendor-inbox" data-vendor-inbox hidden>
                    <div class="navora-panel-heading"><div><span class="navora-panel-kicker">PERMINTAAN DARI BRAND</span><h2>RFQ yang mungkin cocok</h2></div><span class="navora-status-pill status-open">2 baru</span></div>
                    <article class="navora-incoming-rfq"><div class="navora-rfq-icon"><i class="bi bi-cup-hot"></i></div><div class="navora-incoming-copy"><h3>Standing pouch kopi 200 g</h3><p>Brand kopi lokal · target 1.000 pcs · Kraft + laminasi · Bandung</p><span>Contoh RFQ · profil pembeli disamarkan</span></div><button class="btn navora-app-primary" type="button" data-action="respond-rfq" data-rfq="rfq-demo-1">Buat penawaran</button></article>
                    <article class="navora-incoming-rfq"><div class="navora-rfq-icon icon-lilac"><i class="bi bi-tags"></i></div><div class="navora-incoming-copy"><h3>Label botol minuman</h3><p>Brand minuman lokal · target 2.500 pcs · Label tahan air · Jakarta</p><span>Contoh RFQ · profil pembeli disamarkan</span></div><button class="btn navora-app-primary" type="button" data-action="respond-rfq" data-rfq="rfq-demo-2">Buat penawaran</button></article>
                </div>
                <div class="navora-note"><i class="bi bi-info-circle"></i><span>Permintaan dan penawaran di halaman ini hanya simulasi lokal. Harga belum dikonfirmasi vendor dan bukan penawaran mengikat.</span></div>
            </section>

            <section class="navora-page-panel" data-page-panel="assistant" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Bantuan</span><i class="bi bi-chevron-right"></i><span>Asisten kemasan</span></div><h1>Ceritakan produk yang kamu buat</h1><p>Dapatkan ide awal untuk dibicarakan kembali dengan vendor.</p></div><span class="navora-ai-demo-chip"><i class="bi bi-stars"></i> SIMULASI, BUKAN AI LANGSUNG</span></div>
                <div class="navora-assistant-layout">
                    <form class="navora-panel navora-assistant-form" data-assistant-form>
                        <span class="navora-panel-kicker">PANDUAN AWAL</span><h2>Mulai dari kebutuhanmu</h2>
                        <label class="navora-form-label" for="product-name">Produk yang akan dikemas</label><input class="form-control navora-form-control" id="product-name" name="product" required placeholder="Contoh: kopi bubuk, sambal, sabun cair">
                        <div class="navora-form-row"><div><label class="navora-form-label" for="product-size">Ukuran isi produk</label><input class="form-control navora-form-control" id="product-size" name="size" placeholder="Contoh: 200 gram"></div><div><label class="navora-form-label" for="product-volume">Perkiraan kebutuhan</label><select class="form-select navora-form-control" id="product-volume" name="volume"><option value="small">Di bawah 1.000 pcs</option><option value="medium">1.000–5.000 pcs</option><option value="large">Di atas 5.000 pcs</option></select></div></div>
                        <label class="navora-form-label" for="product-preference">Preferensi (opsional)</label><textarea class="form-control navora-form-control" id="product-preference" name="preference" rows="3" placeholder="Contoh: bisa ditutup ulang, tampilan natural"></textarea>
                        <button class="btn navora-app-primary" type="submit"><i class="bi bi-stars"></i> Buat ide awal</button>
                        <p class="navora-form-disclaimer"><i class="bi bi-info-circle"></i> Hasil memakai aturan demo di browser, bukan AI generatif. Bukan saran keamanan pangan, regulasi, atau jaminan kecocokan material.</p>
                    </form>
                    <section class="navora-panel navora-assistant-result" data-assistant-result aria-live="polite">
                        <div class="navora-assistant-placeholder"><span><i class="bi bi-lightbulb"></i></span><h2>Ide kemasanmu akan muncul di sini</h2><p>Isi jenis produk dan kebutuhanmu untuk mendapat arahan diskusi dengan vendor.</p></div>
                    </section>
                </div>
            </section>

            <section class="navora-page-panel" data-page-panel="listings" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Ruang vendor</span><i class="bi bi-chevron-right"></i><span>Katalog saya</span></div><h1>Tampilkan layanan bisnismu</h1><p>Kirim katalog untuk ditinjau admin. Produk baru aktif setelah disetujui.</p></div><button class="btn navora-app-primary" type="button" data-action="add-listing"><i class="bi bi-plus-lg"></i> Tambah katalog</button></div>
                <div class="navora-vendor-setup" data-vendor-listing-notice><span><i class="bi bi-patch-check"></i></span><div><strong data-vendor-listing-notice-copy>Katalog perlu persetujuan admin</strong><p>Produk baru perlu ditinjau admin. Produk hanya aktif dan terlihat UMKM setelah disetujui.</p></div>
                    @if (auth()->check() && auth()->user()->role === 'vendor')
                        <a class="btn navora-app-outline" href="{{ route('vendor.verification.index') }}">Verifikasi profil <i class="bi bi-arrow-right"></i></a>
                    @else
                        <a class="btn navora-app-outline" href="{{ route('login') }}">Masuk untuk mengajukan <i class="bi bi-arrow-right"></i></a>
                    @endif
                </div>
                <div class="navora-empty-state" data-vendor-listings-loading hidden><i class="bi bi-arrow-repeat"></i><strong>Memuat katalog</strong><span>Menyiapkan layanan yang tersimpan di akunmu.</span></div>
                <div class="navora-order-error" data-vendor-listings-error role="alert" hidden></div>
                <div class="navora-empty-state" data-vendor-listings-empty hidden><i class="bi bi-box-seam"></i><strong>Belum ada katalog</strong><span>Tambahkan produk atau layanan untuk diajukan ke admin.</span></div>
                <div class="navora-vendor-listings" data-vendor-listings></div>
            </section>

            <section class="navora-page-panel" data-page-panel="programs" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Ruang mitra</span><i class="bi bi-chevron-right"></i><span>Program</span></div><h1>Dukung usaha bertumbuh bersama</h1><p>Contoh ruang bagi pendamping, inkubator, komunitas, dan mitra ekosistem UMKM.</p></div><span class="navora-ai-demo-chip"><i class="bi bi-buildings"></i> PROGRAM SIMULASI</span></div>
                <div class="navora-program-grid">
                    <article class="navora-panel navora-program-card"><span class="navora-program-icon"><i class="bi bi-mortarboard"></i></span><span class="navora-status-pill status-open">Contoh program</span><h2>Klinik kesiapan kemasan</h2><p>Sesi pendampingan untuk memahami informasi produk, pilihan format kemasan, dan pertanyaan untuk vendor.</p><div><span><i class="bi bi-calendar3"></i> Jadwal simulasi</span><span><i class="bi bi-people"></i> Terbuka untuk UMKM</span></div><button class="btn navora-app-outline" type="button" data-action="program-interest" data-program="Klinik kesiapan kemasan">Tandai minat</button></article>
                    <article class="navora-panel navora-program-card"><span class="navora-program-icon icon-teal"><i class="bi bi-boxes"></i></span><span class="navora-status-pill status-open">Contoh program</span><h2>Kurasi vendor lokal</h2><p>Ruang kolaborasi untuk menghubungkan pelaku usaha dengan vendor kemasan di wilayahnya.</p><div><span><i class="bi bi-geo-alt"></i> Wilayah simulasi</span><span><i class="bi bi-shop-window"></i> Vendor lokal</span></div><button class="btn navora-app-outline" type="button" data-action="program-interest" data-program="Kurasi vendor lokal">Tandai minat</button></article>
                    <article class="navora-panel navora-program-card"><span class="navora-program-icon icon-gold"><i class="bi bi-lightbulb"></i></span><span class="navora-status-pill status-open">Contoh program</span><h2>Literasi produksi dan MOQ</h2><p>Materi edukasi tentang tahapan produksi, perencanaan kebutuhan, dan cara meminta penawaran.</p><div><span><i class="bi bi-journal-text"></i> Materi contoh</span><span><i class="bi bi-person-workspace"></i> Pendampingan</span></div><button class="btn navora-app-outline" type="button" data-action="program-interest" data-program="Literasi produksi dan MOQ">Tandai minat</button></article>
                </div>
                <div class="navora-note"><i class="bi bi-info-circle"></i><span>Program mitra pada prototipe bukan layanan yang sedang dibuka dan tidak mengumpulkan atau meneruskan pendaftaran.</span></div>
            </section>

            <section class="navora-page-panel" data-page-panel="account" role="tabpanel" hidden>
                @if (Auth::check() && in_array(Auth::user()->role, ['umkm', 'vendor'], true))
                    @php
                        $businessProfile = Auth::user()->businessProfile;
                    @endphp
                    <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Pengaturan</span><i class="bi bi-chevron-right"></i><span>Profil usaha</span></div><h1>Profil usahamu</h1><p>Informasi usaha ini tersimpan di akun Navora dan dapat kamu perbarui.</p></div></div>
                    <div class="navora-account-layout">
                        <section class="navora-panel navora-account-card">
                            <div class="navora-account-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</div>
                            <div>
                                <span class="navora-panel-kicker">PROFIL TERSIMPAN</span>
                                <h2>{{ $businessProfile?->business_name ?? Auth::user()->name }}</h2>
                                <p>{{ $businessProfile?->category ?? 'Bidang usaha belum diisi' }} · {{ $businessProfile?->location ?? 'Lokasi belum diisi' }}</p>
                                <span class="navora-status-pill status-waiting">Profil usaha · {{ Auth::user()->role === 'vendor' ? 'Vendor' : 'UMKM' }}</span>
                            </div>
                            <a class="btn navora-app-outline" href="{{ route('business-profile.show') }}">Ubah profil</a>
                        </section>
                        <section class="navora-panel navora-account-details">
                            <div class="navora-panel-heading"><h2>Informasi usaha</h2><span class="navora-demo-label"><i class="bi bi-database-check"></i> TERSIMPAN DI AKUN</span></div>
                            <div class="navora-account-row"><span>Jenis akun</span><strong>{{ Auth::user()->role === 'vendor' ? 'Vendor' : 'UMKM' }}</strong></div>
                            <div class="navora-account-row"><span>Email akun</span><strong>{{ Auth::user()->email }}</strong></div>
                            <div class="navora-account-row"><span>Nomor kontak</span><strong>{{ $businessProfile?->phone ?: 'Belum diisi' }}</strong></div>
                            <div class="navora-account-row"><span>Tentang usaha</span><strong>{{ $businessProfile?->description ?: 'Belum diisi' }}</strong></div>
                            @if (Auth::user()->role === 'vendor')
                                <div class="navora-account-row"><span>Jenis kemasan atau layanan</span><strong>{{ $businessProfile?->product_categories ?: 'Belum diisi' }}</strong></div>
                                <div class="navora-account-row"><span>MOQ</span><strong>{{ $businessProfile?->minimum_order_quantity ? number_format($businessProfile->minimum_order_quantity, 0, ',', '.') . ' pcs' : 'Belum diisi' }}</strong></div>
                                <div class="navora-account-row"><span>Verifikasi vendor</span><strong>{{ ['not_submitted' => 'Belum diajukan', 'pending' => 'Menunggu pemeriksaan', 'approved' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan'][$businessProfile?->verification_status ?? 'not_submitted'] }}</strong></div>
                                <a class="btn navora-app-outline" href="{{ route('vendor.verification.index') }}">Kelola dokumen verifikasi <i class="bi bi-arrow-right"></i></a>
                            @elseif (Auth::check())
                                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Pengaturan</span><i class="bi bi-chevron-right"></i><span>Akun mitra</span></div><h1>Ruang akun mitra</h1><p>Akun mitra yang sudah terdaftar tetap dapat mengakses ruang Navora.</p></div></div>
                                <section class="navora-panel navora-account-details"><div class="navora-panel-heading"><h2>Informasi akun</h2></div><div class="navora-account-row"><span>Nama</span><strong>{{ Auth::user()->name }}</strong></div><div class="navora-account-row"><span>Email</span><strong>{{ Auth::user()->email }}</strong></div><div class="navora-account-row"><span>Peran</span><strong>Mitra (akun lama)</strong></div></section>
                            @else
                                <div class="navora-account-row"><span>Produk</span><strong>{{ $businessProfile?->product_type ?: 'Belum diisi' }}</strong></div>
                                <div class="navora-account-row"><span>Kebutuhan kemasan</span><strong>{{ $businessProfile?->packaging_type ?: 'Belum diisi' }}</strong></div>
                            @endif
                        </section>
                    </div>
                    <section class="navora-panel navora-account-danger">
                        <div>
                            <span class="navora-panel-kicker">TINDAKAN PERMANEN</span>
                            <h2>Hapus akun</h2>
                            <p>Akun dan seluruh profil usaha akan dihapus permanen. Dokumen verifikasi yang tersimpan juga akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                        @if ($errors->has('account'))
                            <p class="navora-account-error" role="alert">{{ $errors->first('account') }}</p>
                        @endif
                        <form action="{{ route('account.destroy') }}" method="POST" data-confirm-submit="Yakin ingin menghapus akun Navora secara permanen? Tindakan ini tidak dapat dibatalkan.">
                            @csrf
                            @method('DELETE')
                            <label class="navora-account-password-label" for="delete-account-password">Konfirmasi dengan kata sandi saat ini</label>
                            <input
                                class="navora-account-password"
                                id="delete-account-password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >
                            @if ($errors->has('password'))
                                <p class="navora-account-error" role="alert">{{ $errors->first('password') }}</p>
                            @endif
                            <button class="navora-account-delete-button" type="submit">Hapus akun secara permanen</button>
                        </form>
                    </section>
                @else
                    <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Pengaturan</span><i class="bi bi-chevron-right"></i><span>Profil demo</span></div><h1>Profil ruang Navora</h1><p>Pengaturan berikut hanya mengubah tampilan di browser ini.</p></div></div>
                    <div class="navora-account-layout">
                        <section class="navora-panel navora-account-card"><div class="navora-account-avatar" data-account-avatar>U</div><div><span class="navora-panel-kicker">PROFIL LOKAL DEMO</span><h2 data-account-name>Usaha Kopi Nusantara</h2><p data-account-role-label>Brand UMKM · Bandung</p><span class="navora-status-pill status-waiting">Belum diverifikasi</span></div><button class="btn navora-app-outline" type="button" data-action="edit-profile">Edit profil</button></section>
                        <section class="navora-panel navora-account-details"><div class="navora-panel-heading"><h2>Informasi akun</h2><span class="navora-demo-label"><i class="bi bi-circle-fill"></i> TERSIMPAN DI BROWSER</span></div><div class="navora-account-row"><span>Jenis akun</span><strong data-account-type>Brand UMKM</strong></div><div class="navora-account-row"><span>Lokasi</span><strong data-account-location>Bandung, Jawa Barat</strong></div><div class="navora-account-row"><span>Verifikasi identitas</span><strong>Belum tersedia dalam prototipe</strong></div><div class="navora-account-row"><span>Metode pembayaran</span><strong>Tidak terhubung</strong></div></section>
                    </div>
                @endauth
            </section>
                </div>
                @include('advertisements.sidebar')
            </div>
            <footer class="navora-app-footer">
                <span>Navora · Prototype</span>
                @if (Auth::check() && in_array(Auth::user()->role, ['umkm', 'vendor'], true))
                    <span>Pesanan tersimpan di akun; halaman lain masih memiliki konten demo.</span>
                @else
                    <span>Data dan tindakan demo disimpan lokal di browser; tidak ada transaksi, pembayaran, atau verifikasi nyata.</span>
                @endif
                <a href="{{ route('home') }}">Kembali ke beranda <i class="bi bi-arrow-up-right"></i></a>
            </footer>
        </main>
    </div>

    <div class="navora-modal" data-modal hidden>
        <button class="navora-modal-backdrop" type="button" data-close-modal aria-label="Tutup dialog"></button>
        <section class="navora-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="navora-modal-header"><span class="navora-modal-icon"><i class="bi bi-stars" data-modal-icon></i></span><button class="navora-modal-close" type="button" data-close-modal aria-label="Tutup"><i class="bi bi-x-lg"></i></button></div>
            <h2 id="modal-title" data-modal-title>Navora demo</h2>
            <p data-modal-description></p>
            <form data-modal-form>
                <div class="navora-modal-fields" data-modal-fields></div>
                <div class="navora-modal-actions"><button class="btn navora-app-outline" type="button" data-close-modal>Batal</button><button class="btn navora-app-primary" type="submit" data-modal-submit>Simpan di demo</button></div>
            </form>
        </section>
    </div>
    <div class="navora-toast" role="status" aria-live="polite" data-toast></div>
    <script src="{{ asset('js/navora-app.js').'?v='.filemtime(public_path('js/navora-app.js')) }}" defer></script>
</body>
</html>
