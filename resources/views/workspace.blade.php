<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Demo marketplace Navora untuk mencari vendor kemasan, membandingkan penawaran, dan bergabung dalam patungan MOQ.">
    <title>Ruang Navora — Marketplace kemasan UMKM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/navora-app.css') }}" rel="stylesheet">
</head>
<body class="navora-app-body"
    data-auth-role="{{ auth()->user()->role ?? '' }}"
    data-auth-name="{{ auth()->user()->name ?? '' }}"
    data-auth-user-id="{{ auth()->id() ?? '' }}">
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
                    <span class="navora-demo-label"><i class="bi bi-circle-fill"></i> MODE DEMO</span>
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
                        <button class="navora-page-link" type="button" data-page="groups" data-roles="umkm,vendor" role="tab" aria-selected="false"><i class="bi bi-people"></i> Patungan MOQ</button>
                        <button class="navora-page-link" type="button" data-page="rfq" data-roles="umkm,vendor" role="tab" aria-selected="false"><i class="bi bi-chat-square-text"></i> Permintaan penawaran</button>
                        <button class="navora-page-link" type="button" data-page="assistant" data-roles="umkm" role="tab" aria-selected="false"><i class="bi bi-stars"></i> Asisten kemasan</button>
                        <button class="navora-page-link" type="button" data-page="programs" data-roles="partner" role="tab" aria-selected="false"><i class="bi bi-buildings"></i> Program mitra</button>
                        <button class="navora-page-link" type="button" data-page="account" data-roles="umkm,vendor,partner" role="tab" aria-selected="false"><i class="bi bi-person"></i> @auth Profil usaha @else Profil demo @endauth</button>
                    </div>
                    <a class="navora-back-home" href="{{ route('home') }}"><i class="bi bi-arrow-left"></i><span>Beranda</span></a>
                </div>
            </nav>
        </header>

        <main class="navora-app-main container-fluid" id="app-content">
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
                    <article><span class="navora-stat-icon stat-teal"><i class="bi bi-people"></i></span><div><span>Patungan terbuka</span><strong data-stat-groups>3</strong><small>Masih mencari peserta</small></div></article>
                    <article><span class="navora-stat-icon stat-gold"><i class="bi bi-chat-square-text"></i></span><div><span>Permintaan saya</span><strong data-stat-rfqs>2</strong><small>Contoh untuk perbandingan</small></div></article>
                    <article><span class="navora-stat-icon stat-lilac"><i class="bi bi-lightbulb"></i></span><div><span>Ide kemasan</span><strong>Siap</strong><small>Asisten berbasis demo</small></div></article>
                </div>
                <div class="navora-overview-stats" data-role-content="vendor" hidden>
                    <article><span class="navora-stat-icon stat-blue"><i class="bi bi-box-seam"></i></span><div><span>Katalog demo</span><strong data-stat-listings>3</strong><small>Produk ditampilkan</small></div></article>
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
                        <div class="navora-vendor-opportunity"><span><i class="bi bi-people"></i></span><div><strong>Patungan kemasan makanan</strong><p>Beberapa usaha sedang mencari vendor box kraft.</p><small>Sudah terkumpul 68% dari target</small></div><button type="button" class="btn navora-app-outline" data-open-page="groups">Lihat</button></div>
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
                        <label class="navora-form-label" for="filter-category">Jenis layanan</label><select class="form-select navora-form-control" id="filter-category" data-vendor-filter="category"><option value="">Semua jenis</option><option value="packaging">Kemasan</option><option value="printing">Percetakan</option><option value="maklon">Maklon</option></select>
                        <label class="navora-form-label" for="filter-moq">MOQ maksimum</label><select class="form-select navora-form-control" id="filter-moq" data-vendor-filter="moq"><option value="">Semua MOQ</option><option value="500">≤ 500 pcs</option><option value="1000">≤ 1.000 pcs</option><option value="5000">≤ 5.000 pcs</option></select>
                        <label class="navora-form-label" for="filter-material">Bahan</label><select class="form-select navora-form-control" id="filter-material" data-vendor-filter="material"><option value="">Semua bahan</option><option value="kraft">Kraft paper</option><option value="pet">PET</option><option value="pouch">Laminasi pouch</option><option value="carton">Karton</option></select>
                        <label class="navora-form-label" for="filter-location">Lokasi</label><select class="form-select navora-form-control" id="filter-location" data-vendor-filter="location"><option value="">Semua lokasi</option><option value="bandung">Bandung</option><option value="jakarta">Jakarta</option><option value="surabaya">Surabaya</option><option value="yogyakarta">Yogyakarta</option></select>
                        <label class="navora-checkbox"><input type="checkbox" data-vendor-filter="sample"><span>Tersedia opsi sampel</span></label>
                        <label class="navora-checkbox"><input type="checkbox" data-vendor-filter="verified"><span>Profil bertanda terverifikasi*</span></label>
                        <p class="navora-filter-footnote">*Label status hanya contoh pada prototipe.</p>
                    </aside>
                    <div class="navora-vendor-results">
                        <div class="navora-results-heading"><span><strong data-vendor-result-count>4</strong> pilihan untuk dieksplorasi</span><label for="vendor-sort" class="visually-hidden">Urutkan vendor</label><select id="vendor-sort" class="form-select navora-sort-select" data-vendor-sort><option value="recommended">Rekomendasi</option><option value="price">Harga indikatif terendah</option><option value="moq">MOQ terkecil</option></select></div>
                        <div class="navora-vendor-grid" data-vendor-grid>
                            <article class="navora-vendor-card" data-vendor-card data-id="pouchkita" data-name="PouchKita Studio" data-category="packaging" data-product="Standing pouch kopi" data-moq="500" data-material="pouch kraft" data-location="bandung" data-price="1250" data-sample="true" data-verified="true" data-tags="pouch kopi standing pouch cetak">
                                <div class="navora-vendor-art art-pouch"><span><i class="bi bi-bag"></i></span><small>FOTO PRODUK DEMO</small><span class="navora-verified-tag"><i class="bi bi-patch-check-fill"></i> Profil demo</span></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>PouchKita Studio</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Bandung</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,9 <small>(demo)</small></span></div><p>Standing pouch custom untuk kopi, snack, dan produk kering.</p><div class="navora-vendor-tags"><span>Pouch laminasi</span><span>Kraft</span><span>Sampel tersedia</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>500 pcs</strong></span><span>Harga indikatif<strong>Rp1.250 / pcs</strong></span><span>Estimasi produksi<strong>14–21 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="PouchKita Studio">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="PouchKita Studio">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="kemasprima" data-name="Kemas Prima" data-category="packaging" data-product="Botol PET" data-moq="1000" data-material="pet" data-location="jakarta" data-price="850" data-sample="true" data-verified="true" data-tags="botol pet minuman kosmetik">
                                <div class="navora-vendor-art art-bottle"><span><i class="bi bi-cup-straw"></i></span><small>FOTO PRODUK DEMO</small><span class="navora-verified-tag"><i class="bi bi-patch-check-fill"></i> Profil demo</span></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Kemas Prima</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Jakarta</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,8 <small>(demo)</small></span></div><p>Botol PET dan tutup untuk minuman serta perawatan tubuh.</p><div class="navora-vendor-tags"><span>PET</span><span>Botol</span><span>Sampel tersedia</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>1.000 pcs</strong></span><span>Harga indikatif<strong>Rp850 / pcs</strong></span><span>Estimasi produksi<strong>10–18 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Kemas Prima">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Kemas Prima">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="cetakbumi" data-name="Cetak Bumi" data-category="printing" data-product="Box karton kraft" data-moq="300" data-material="kraft carton" data-location="yogyakarta" data-price="2100" data-sample="false" data-verified="false" data-tags="cetak box karton kraft paper">
                                <div class="navora-vendor-art art-box"><span><i class="bi bi-box2"></i></span><small>FOTO PRODUK DEMO</small></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Cetak Bumi</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Yogyakarta</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,7 <small>(demo)</small></span></div><p>Cetak digital kemasan kertas dan box untuk batch terbatas.</p><div class="navora-vendor-tags"><span>Kraft paper</span><span>Karton</span><span>Batch kecil</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>300 pcs</strong></span><span>Harga indikatif<strong>Rp2.100 / pcs</strong></span><span>Estimasi produksi<strong>7–14 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Cetak Bumi">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Cetak Bumi">Minta penawaran</button></div></div>
                            </article>
                            <article class="navora-vendor-card" data-vendor-card data-id="rasa-racik" data-name="Rasa Racik Maklon" data-category="maklon" data-product="Maklon minuman serbuk" data-moq="5000" data-material="carton" data-location="surabaya" data-price="3200" data-sample="true" data-verified="false" data-tags="maklon minuman serbuk produksi">
                                <div class="navora-vendor-art art-maklon"><span><i class="bi bi-cup-hot"></i></span><small>FOTO PRODUK DEMO</small></div>
                                <div class="navora-vendor-content"><div class="navora-vendor-name-row"><div><h2>Rasa Racik Maklon</h2><span class="navora-vendor-location"><i class="bi bi-geo-alt"></i> Surabaya</span></div><span class="navora-rating"><i class="bi bi-star-fill"></i> 4,8 <small>(demo)</small></span></div><p>Jasa maklon minuman serbuk dengan diskusi formula awal.</p><div class="navora-vendor-tags"><span>Maklon</span><span>Minuman serbuk</span><span>Konsultasi</span></div><div class="navora-vendor-specs"><span>MOQ mulai<strong>5.000 pcs</strong></span><span>Harga indikatif<strong>Rp3.200 / pcs</strong></span><span>Estimasi produksi<strong>21–35 hari</strong></span></div><div class="navora-vendor-actions"><button class="btn navora-app-outline" type="button" data-action="vendor-detail" data-vendor="Rasa Racik Maklon">Lihat detail</button><button class="btn navora-app-primary" type="button" data-action="rfq" data-vendor="Rasa Racik Maklon">Minta penawaran</button></div></div>
                            </article>
                        </div>
                        <div class="navora-empty-state" data-vendor-empty hidden><i class="bi bi-search"></i><strong>Belum ada vendor yang cocok</strong><span>Coba ubah kata kunci atau longgarkan filternya.</span></div>
                    </div>
                </div>
                <div class="navora-note"><i class="bi bi-info-circle"></i><span>Profil, peringkat, MOQ, harga, dan estimasi pada katalog adalah contoh untuk demo. Verifikasi dokumen belum dilakukan.</span></div>
            </section>

            <section class="navora-page-panel" data-page-panel="groups" role="tabpanel" hidden>
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Kolaborasi</span><i class="bi bi-chevron-right"></i><span>Patungan MOQ</span></div><h1>Pesan bersama, mulai lebih ringan</h1><p>Jelajahi contoh kebutuhan yang bisa digabungkan untuk memenuhi jumlah minimum vendor.</p></div><button class="btn navora-app-primary" type="button" data-action="create-group"><i class="bi bi-plus-lg"></i> Buat patungan</button></div>
                <div class="navora-group-callout"><span><i class="bi bi-info-circle"></i></span><div><strong>Perhatikan syarat sebelum bergabung</strong><p>Jumlah peserta, spesifikasi, pembagian barang, jadwal, dan mekanisme pembayaran nyata perlu disepakati dengan jelas. Tombol di demo ini hanya menyimpan status partisipasi lokal.</p></div></div>
                <div class="navora-group-grid" data-group-grid>
                    <article class="navora-group-card" data-group-card data-id="group-coffee" data-name="Pouch kopi kraft 200 g" data-total="1000" data-joined="720" data-category="Pouch" data-vendor="PouchKita Studio" data-due="5 hari lagi">
                        <div class="navora-group-card-top"><span class="navora-group-category"><i class="bi bi-bag"></i> Pouch kopi</span><span class="navora-group-status"><i></i> Mencari peserta</span></div><h2>Pouch kopi kraft 200 g</h2><p>Standing pouch dengan zipper · desain dibicarakan setelah MOQ tercapai.</p><div class="navora-group-vendor"><span class="navora-vendor-avatar">PK</span><span><strong>PouchKita Studio</strong><small>Vendor demo · Bandung</small></span><button type="button" class="navora-more-button" aria-label="Info vendor"><i class="bi bi-three-dots"></i></button></div><div class="navora-group-progress-label"><span>Progres patungan</span><strong><span data-group-joined>720</span> / 1.000 pcs</strong></div><div class="progress navora-progress"><div class="progress-bar" data-group-progress style="width:72%"></div></div><div class="navora-group-foot"><span><i class="bi bi-people"></i> <b data-group-percent>72%</b> terkumpul</span><span><i class="bi bi-clock"></i> 5 hari lagi</span></div><button class="btn navora-app-primary navora-group-join" type="button" data-action="join-group" data-group="group-coffee">Ikut patungan <i class="bi bi-arrow-right"></i></button></article>
                    <article class="navora-group-card" data-group-card data-id="group-box" data-name="Box kraft snack" data-total="2000" data-joined="1360" data-category="Box kraft" data-vendor="Cetak Bumi" data-due="8 hari lagi">
                        <div class="navora-group-card-top"><span class="navora-group-category"><i class="bi bi-box2"></i> Box makanan</span><span class="navora-group-status"><i></i> Mencari peserta</span></div><h2>Box kraft snack</h2><p>Box kertas food-grade, opsi cetak satu warna sesuai diskusi vendor.</p><div class="navora-group-vendor"><span class="navora-vendor-avatar avatar-green">CB</span><span><strong>Cetak Bumi</strong><small>Vendor demo · Yogyakarta</small></span><button type="button" class="navora-more-button" aria-label="Info vendor"><i class="bi bi-three-dots"></i></button></div><div class="navora-group-progress-label"><span>Progres patungan</span><strong><span data-group-joined>1.360</span> / 2.000 pcs</strong></div><div class="progress navora-progress"><div class="progress-bar" data-group-progress style="width:68%"></div></div><div class="navora-group-foot"><span><i class="bi bi-people"></i> <b data-group-percent>68%</b> terkumpul</span><span><i class="bi bi-clock"></i> 8 hari lagi</span></div><button class="btn navora-app-primary navora-group-join" type="button" data-action="join-group" data-group="group-box">Ikut patungan <i class="bi bi-arrow-right"></i></button></article>
                    <article class="navora-group-card" data-group-card data-id="group-bottle" data-name="Botol minuman 250 ml" data-total="1500" data-joined="840" data-category="Botol PET" data-vendor="Kemas Prima" data-due="11 hari lagi">
                        <div class="navora-group-card-top"><span class="navora-group-category"><i class="bi bi-cup-straw"></i> Botol PET</span><span class="navora-group-status"><i></i> Mencari peserta</span></div><h2>Botol minuman 250 ml</h2><p>Botol PET bening dengan tutup ulir; warna dan tutup dikonfirmasi vendor.</p><div class="navora-group-vendor"><span class="navora-vendor-avatar avatar-blue">KP</span><span><strong>Kemas Prima</strong><small>Vendor demo · Jakarta</small></span><button type="button" class="navora-more-button" aria-label="Info vendor"><i class="bi bi-three-dots"></i></button></div><div class="navora-group-progress-label"><span>Progres patungan</span><strong><span data-group-joined>840</span> / 1.500 pcs</strong></div><div class="progress navora-progress"><div class="progress-bar" data-group-progress style="width:56%"></div></div><div class="navora-group-foot"><span><i class="bi bi-people"></i> <b data-group-percent>56%</b> terkumpul</span><span><i class="bi bi-clock"></i> 11 hari lagi</span></div><button class="btn navora-app-primary navora-group-join" type="button" data-action="join-group" data-group="group-bottle">Ikut patungan <i class="bi bi-arrow-right"></i></button></article>
                </div>
                <div class="navora-note"><i class="bi bi-exclamation-circle"></i><span>Tidak ada uang yang ditagih atau pesanan yang dibuat. Pengelolaan pembayaran, pembatalan, pengembalian dana, dan penyelesaian sengketa belum tersedia di demo ini.</span></div>
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
                <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Ruang vendor</span><i class="bi bi-chevron-right"></i><span>Katalog saya</span></div><h1>Tampilkan layanan bisnismu</h1><p>Kelola contoh katalog dan profil vendor pada sesi demo ini.</p></div><button class="btn navora-app-primary" type="button" data-action="add-listing"><i class="bi bi-plus-lg"></i> Tambah katalog</button></div>
                <div class="navora-vendor-setup"><span><i class="bi bi-patch-check"></i></span><div><strong>Lengkapi profil sebelum menerima permintaan</strong><p>Ajukan dokumen usaha untuk diperiksa admin Navora. Persetujuan verifikasi akan ditampilkan di profil vendor.</p></div>
                    @if (auth()->check() && auth()->user()->role === 'vendor')
                        <a class="btn navora-app-outline" href="{{ route('vendor.verification.index') }}">Verifikasi profil <i class="bi bi-arrow-right"></i></a>
                    @else
                        <button class="btn navora-app-outline" type="button" data-action="verify-profile">Lihat status demo <i class="bi bi-arrow-right"></i></button>
                    @endif
                </div>
                <div class="navora-vendor-listings" data-vendor-listings>
                    <article class="navora-listing-card" data-listing-card><span class="navora-listing-art art-pouch"><i class="bi bi-bag"></i></span><div><h2>Standing pouch custom</h2><p>MOQ 500 pcs · Pouch laminasi · Sampel tersedia</p><small><i class="bi bi-eye"></i> Aktif di katalog demo</small></div><span class="navora-status-pill status-open">Aktif</span><button class="navora-more-button" type="button" aria-label="Opsi katalog"><i class="bi bi-three-dots"></i></button></article>
                    <article class="navora-listing-card" data-listing-card><span class="navora-listing-art art-box"><i class="bi bi-box2"></i></span><div><h2>Box kemasan makanan</h2><p>MOQ 1.000 pcs · Kraft paper · Cetak satu warna</p><small><i class="bi bi-eye"></i> Aktif di katalog demo</small></div><span class="navora-status-pill status-open">Aktif</span><button class="navora-more-button" type="button" aria-label="Opsi katalog"><i class="bi bi-three-dots"></i></button></article>
                    <article class="navora-listing-card" data-listing-card><span class="navora-listing-art art-bottle"><i class="bi bi-cup-straw"></i></span><div><h2>Konsultasi kemasan awal</h2><p>Diskusi kebutuhan · Rekomendasi material · Sesi sampel</p><small><i class="bi bi-eye"></i> Aktif di katalog demo</small></div><span class="navora-status-pill status-open">Aktif</span><button class="navora-more-button" type="button" aria-label="Opsi katalog"><i class="bi bi-three-dots"></i></button></article>
                </div>
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
                @else
                    <div class="navora-page-title-row"><div><div class="navora-breadcrumb"><span>Pengaturan</span><i class="bi bi-chevron-right"></i><span>Profil demo</span></div><h1>Profil ruang Navora</h1><p>Pengaturan berikut hanya mengubah tampilan di browser ini.</p></div></div>
                    <div class="navora-account-layout">
                        <section class="navora-panel navora-account-card"><div class="navora-account-avatar" data-account-avatar>U</div><div><span class="navora-panel-kicker">PROFIL LOKAL DEMO</span><h2 data-account-name>Usaha Kopi Nusantara</h2><p data-account-role-label>Brand UMKM · Bandung</p><span class="navora-status-pill status-waiting">Belum diverifikasi</span></div><button class="btn navora-app-outline" type="button" data-action="edit-profile">Edit profil</button></section>
                        <section class="navora-panel navora-account-details"><div class="navora-panel-heading"><h2>Informasi akun</h2><span class="navora-demo-label"><i class="bi bi-circle-fill"></i> TERSIMPAN DI BROWSER</span></div><div class="navora-account-row"><span>Jenis akun</span><strong data-account-type>Brand UMKM</strong></div><div class="navora-account-row"><span>Lokasi</span><strong data-account-location>Bandung, Jawa Barat</strong></div><div class="navora-account-row"><span>Verifikasi identitas</span><strong>Belum tersedia dalam prototipe</strong></div><div class="navora-account-row"><span>Metode pembayaran</span><strong>Tidak terhubung</strong></div></section>
                    </div>
                @endauth
            </section>
            <footer class="navora-app-footer">
                <span>Navora · Prototype</span>
                @if (Auth::check() && in_array(Auth::user()->role, ['umkm', 'vendor'], true))
                    <span>Profil usaha tersimpan di akun; pengajuan verifikasi vendor tersedia untuk pemeriksaan admin.</span>
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
    <script src="{{ asset('js/navora-app.js') }}" defer></script>
</body>
</html>
