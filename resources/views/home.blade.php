<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Navora membantu UMKM menemukan vendor kemasan, membandingkan penawaran, dan memenuhi MOQ lewat patungan.">
    <title>Navora — Kemasan yang tepat, mulai dari skala UMKM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/home.css') }}" rel="stylesheet">
    <link href="{{ asset('css/advertisements.css') }}" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>
    <div class="home-shell navora-landing">
        <div class="navora-topline">
            <div class="container d-flex justify-content-between align-items-center gap-3">
                <span><i class="bi bi-stars me-2"></i>Temukan vendor kemasan yang cocok untuk bisnismu</span>
                <a href="{{ route('workspace') }}">Jelajahi demo <i class="bi bi-arrow-up-right ms-1"></i></a>
            </div>
        </div>
        <nav class="navbar navbar-expand-xl navora-navbar sticky-top" aria-label="Navigasi utama">
            <div class="container">
                <a class="navbar-brand navora-wordmark" href="{{ route('home') }}" aria-label="Navora beranda">
                    <span class="navora-logo"><i class="bi bi-box-seam"></i></span>Navora<span class="navora-wordmark-dot">.</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navoraHomeNav" aria-controls="navoraHomeNav" aria-expanded="false" aria-label="Buka navigasi">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navoraHomeNav">
                    <ul class="navbar-nav mx-auto gap-xl-2">
                        <li class="nav-item"><a class="nav-link" href="#cara-kerja">Cara kerja</a></li>
                        <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
                        <li class="nav-item"><a class="nav-link" href="#untuk-siapa">Untuk siapa</a></li>
                    </ul>
                    <div class="d-flex flex-column flex-xl-row gap-2 navora-nav-actions">
                        @auth
                            <span class="navora-nav-login">Hai, {{ Auth::user()->name }}</span>
                            <form action="{{ route('logout') }}" method="post">
                                @csrf
                                <button class="navora-nav-login navora-nav-logout" type="submit">Keluar</button>
                            </form>
                        @else
                            <a class="navora-nav-login" href="{{ route('login') }}">Masuk</a>
                            <a class="btn navora-button navora-button-primary" href="{{ route('register') }}">Daftar gratis <i class="bi bi-arrow-right"></i></a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <main id="konten-utama">
            @include('advertisements.banner')
            <section class="navora-hero">
                <div class="container navora-hero-grid">
                    <div class="navora-hero-copy" data-reveal>
                        <span class="navora-eyebrow"><span></span> Marketplace kemasan untuk UMKM Indonesia</span>
                        <h1>Kemasan yang tepat, <em>tanpa harus pesan berlebihan.</em></h1>
                        <p class="navora-hero-description">Temukan vendor kemasan dan maklon, bandingkan penawaran, atau patungan dengan UMKM lain agar kebutuhan MOQ lebih terjangkau.</p>
                        <form class="navora-search" action="{{ route('workspace') }}" method="get" role="search">
                            <i class="bi bi-search"></i>
                            <label class="visually-hidden" for="navora-home-search">Cari kemasan atau vendor</label>
                            <input id="navora-home-search" type="search" name="q" placeholder="Cari pouch, botol, vendor..." autocomplete="off">
                            <button class="btn navora-button navora-button-primary" type="submit">Cari vendor <i class="bi bi-arrow-right"></i></button>
                        </form>
                        <div class="navora-search-hints">
                            <span>Populer:</span>
                            <a href="{{ route('workspace', ['q' => 'pouch']) }}">Pouch</a>
                            <a href="{{ route('workspace', ['q' => 'botol']) }}">Botol</a>
                            <a href="{{ route('workspace', ['q' => 'maklon']) }}">Maklon</a>
                        </div>
                        <div class="navora-hero-proof">
                            <span><i class="bi bi-check-circle-fill"></i> Vendor terkurasi</span>
                            <span><i class="bi bi-check-circle-fill"></i> MOQ transparan</span>
                            <span><i class="bi bi-check-circle-fill"></i> Pilihan patungan</span>
                        </div>
                    </div>
                    <div class="navora-hero-visual" data-reveal>
                        <div class="navora-hero-orb navora-hero-orb-one"></div>
                        <div class="navora-hero-orb navora-hero-orb-two"></div>
                        <div class="navora-preview">
                            <div class="navora-preview-head">
                                <span class="navora-preview-mark"><i class="bi bi-box-seam"></i></span>
                                <div><strong>Temukan vendor</strong><small>Rekomendasi untuk produkmu</small></div>
                                <span class="navora-preview-menu"><i class="bi bi-three-dots"></i></span>
                            </div>
                            <div class="navora-preview-search"><i class="bi bi-search"></i> Kemasan kopi <span>Filter <i class="bi bi-sliders"></i></span></div>
                            <div class="navora-preview-label">PILIHAN VENDOR</div>
                            <article class="navora-preview-vendor">
                                <span class="navora-preview-product navora-product-pouch"><img src="{{ asset('images/products/coffee.jpg') }}" alt="Kemasan pouch kopi" loading="lazy"></span>
                                <div class="navora-preview-vendor-copy"><strong>PouchKita Studio</strong><span>Standing pouch · MOQ 500 pcs</span><small><i class="bi bi-star-fill"></i> 4,9 <b>·</b> Bandung</small></div>
                                <span class="navora-preview-price">Mulai<br><strong>Rp1.250</strong></span>
                            </article>
                            <article class="navora-preview-vendor">
                                <span class="navora-preview-product navora-product-bottle"><img src="{{ asset('images/products/bottle.jpg') }}" alt="Botol minum" loading="lazy"></span>
                                <div class="navora-preview-vendor-copy"><strong>Kemas Prima</strong><span>Botol PET · MOQ 1.000 pcs</span><small><i class="bi bi-star-fill"></i> 4,8 <b>·</b> Jakarta</small></div>
                                <span class="navora-preview-price">Mulai<br><strong>Rp850</strong></span>
                            </article>
                            <div class="navora-preview-group">
                                <span><i class="bi bi-people-fill"></i></span>
                                <div><strong>Patungan pesanan</strong><small>Sudah terkumpul 72% dari MOQ</small></div>
                                <i class="bi bi-arrow-up-right"></i>
                            </div>
                            <div class="navora-preview-foot"><span><i class="bi bi-shield-check"></i> Contoh tampilan platform</span><span>Demo</span></div>
                        </div>
                        <div class="navora-float-chip"><span><i class="bi bi-people-fill"></i></span><div><strong>Patungan MOQ</strong><small>Pesan sesuai kebutuhan</small></div></div>
                    </div>
                    @include('advertisements.sidebar')
                </div>
                <div class="container navora-hero-bottom">
                    <span>DIRANCANG UNTUK EKOSISTEM UMKM</span>
                    <div><span><i class="bi bi-shop-window"></i> Brand UMKM</span><span><i class="bi bi-boxes"></i> Vendor kemasan</span><span><i class="bi bi-building"></i> Mitra ekosistem</span></div>
                </div>
            </section>

            <section class="navora-section navora-problem" id="cara-kerja">
                <div class="container">
                    <div class="navora-section-heading text-center" data-reveal>
                        <span class="navora-eyebrow navora-eyebrow-dark"><span></span> Dari kebutuhan ke pilihan yang jelas</span>
                        <h2>Mulai dari jumlah kecil.<br><em>Tumbuh dengan lebih siap.</em></h2>
                        <p>MOQ besar dan informasi vendor yang tersebar bisa menghambat produk bagus untuk berkembang. Navora membantu mempertemukan kebutuhan UMKM dengan pilihan vendor yang relevan.</p>
                    </div>
                    <div class="row g-3 g-lg-4 navora-problem-grid">
                        <div class="col-md-4" data-reveal><article class="navora-feature-card"><span class="navora-card-icon"><i class="bi bi-search"></i></span><span class="navora-card-number">01</span><h3>Cari lebih terarah</h3><p>Telusuri kemasan, percetakan, dan jasa maklon dengan filter MOQ, bahan, lokasi, serta kebutuhan sertifikasi.</p><a href="{{ route('workspace') }}">Cari vendor <i class="bi bi-arrow-right"></i></a></article></div>
                        <div class="col-md-4" data-reveal><article class="navora-feature-card navora-feature-card-highlight"><span class="navora-card-icon"><i class="bi bi-people"></i></span><span class="navora-card-number">02</span><h3>Patungan capai MOQ</h3><p>Gabungkan kebutuhan dengan UMKM lain pada penawaran yang cocok untuk mencapai jumlah minimum vendor.</p><a href="{{ route('workspace', ['tab' => 'groups']) }}">Lihat patungan <i class="bi bi-arrow-right"></i></a></article></div>
                        <div class="col-md-4" data-reveal><article class="navora-feature-card"><span class="navora-card-icon"><i class="bi bi-chat-square-text"></i></span><span class="navora-card-number">03</span><h3>Bandingkan penawaran</h3><p>Kirim satu permintaan kebutuhan dan tinjau harga, kapasitas, waktu produksi, serta syarat dari vendor.</p><a href="{{ route('workspace', ['tab' => 'rfq']) }}">Buat permintaan <i class="bi bi-arrow-right"></i></a></article></div>
                    </div>
                </div>
            </section>

            <section class="navora-section navora-ai-section" id="fitur">
                <div class="container navora-ai-grid">
                    <div class="navora-ai-visual" data-reveal>
                        <div class="navora-ai-card">
                            <span class="navora-ai-kicker"><i class="bi bi-stars"></i> ASISTEN KEMASAN</span>
                            <h3>Ceritakan produkmu</h3>
                            <div class="navora-ai-input">Kopi bubuk, 200 gram<span><i class="bi bi-arrow-up"></i></span></div>
                            <div class="navora-ai-suggestion"><span><i class="bi bi-lightbulb"></i></span><div><strong>Ide yang bisa dipertimbangkan</strong><p>Pouch kraft dengan lapisan pelindung mungkin sesuai. Tanyakan opsi food-grade dan sampel kepada vendor.</p></div></div>
                            <div class="navora-ai-tags"><span>Pouch</span><span>Food-grade</span><span>Perlu cek vendor</span></div>
                            <div class="navora-ai-disclaimer"><i class="bi bi-info-circle"></i> Saran awal, bukan penentuan keamanan pangan atau kepatuhan regulasi.</div>
                        </div>
                        <span class="navora-ai-orbit"></span>
                    </div>
                    <div class="navora-ai-copy" data-reveal>
                        <span class="navora-eyebrow navora-eyebrow-dark"><span></span> Bukan cuma direktori vendor</span>
                        <h2>Lebih percaya diri memilih <em>kemasan berikutnya.</em></h2>
                        <p>Asisten Navora dirancang untuk membantu menyusun ide awal berdasarkan produk dan kebutuhanmu, lalu menemukan vendor yang dapat kamu tanyai lebih lanjut.</p>
                        <ul>
                            <li><i class="bi bi-check-lg"></i> Rekomendasi tipe kemasan untuk dieksplorasi</li>
                            <li><i class="bi bi-check-lg"></i> Pertanyaan penting tentang bahan dan sampel</li>
                            <li><i class="bi bi-check-lg"></i> Kandidat vendor untuk dibandingkan</li>
                        </ul>
                        <a class="btn navora-button navora-button-primary" href="{{ route('workspace', ['tab' => 'assistant']) }}">Coba asisten demo <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </section>

            <section class="navora-section navora-audience-section" id="untuk-siapa">
                <div class="container">
                    <div class="navora-section-heading text-center" data-reveal>
                        <span class="navora-eyebrow navora-eyebrow-dark"><span></span> Satu ruang, banyak peluang kolaborasi</span>
                        <h2>Navora tumbuh bersama<br><em>ekosistem UMKM.</em></h2>
                    </div>
                    <div class="row g-3 g-lg-4">
                        <div class="col-md-4" data-reveal><article class="navora-audience-card"><span class="navora-audience-icon"><i class="bi bi-bag-heart"></i></span><h3>Pemilik brand UMKM</h3><p>Temukan kemasan yang sesuai skala usaha, minta sampel, bandingkan vendor, dan jelajahi kesempatan patungan.</p><a href="{{ route('workspace', ['role' => 'umkm']) }}">Jelajahi sebagai UMKM <i class="bi bi-arrow-right"></i></a></article></div>
                        <div class="col-md-4" data-reveal><article class="navora-audience-card"><span class="navora-audience-icon"><i class="bi bi-box-seam"></i></span><h3>Vendor dan maklon</h3><p>Tampilkan katalog dan kapasitas layanan agar brand yang cocok lebih mudah menemukan bisnismu.</p><a href="{{ route('workspace', ['role' => 'vendor']) }}">Jelajahi sebagai vendor <i class="bi bi-arrow-right"></i></a></article></div>
                        <div class="col-md-4" data-reveal><article class="navora-audience-card"><span class="navora-audience-icon"><i class="bi bi-buildings"></i></span><h3>Mitra pendukung UMKM</h3><p>Bantu pelaku usaha menemukan sumber daya, pendampingan, dan jaringan pengembangan produk.</p><a href="{{ route('workspace', ['role' => 'partner']) }}">Lihat ruang mitra <i class="bi bi-arrow-right"></i></a></article></div>
                    </div>
                </div>
            </section>
            <section class="navora-bottom-cta">
                <div class="container navora-cta-inner" data-reveal>
                    <div><span class="navora-eyebrow"><span></span> Siap mencari kemungkinan baru?</span><h2>Temukan partner kemasan<br>untuk langkah berikutnya.</h2></div>
                    <a class="btn navora-button navora-button-light" href="{{ route('workspace') }}">Masuk ke demo Navora <i class="bi bi-arrow-up-right"></i></a>
                </div>
            </section>
        </main>
        <footer class="navora-footer">
            <div class="container navora-footer-inner">
                <a class="navora-wordmark navora-footer-brand" href="{{ route('home') }}"><span class="navora-logo"><i class="bi bi-box-seam"></i></span>Navora<span class="navora-wordmark-dot">.</span></a>
                <p>Marketplace kemasan dan maklon untuk ekosistem UMKM Indonesia.</p>
                <small>Konsep dan interaksi pada demo ini menggunakan data simulasi; ketersediaan vendor dan transaksi nyata belum terhubung.</small>
                <span>© {{ date('Y') }} Navora · Prototype</span>
            </div>
        </footer>
        <button class="navora-back-top" type="button" aria-label="Kembali ke atas"><i class="bi bi-arrow-up"></i></button>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/home.js') }}" defer></script>
</body>
</html>
