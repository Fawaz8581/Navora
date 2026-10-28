<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f2f7fc">
        <meta name="description" content="Kelola profil supplier, purchase order, pengiriman, invoice, dan pembayaran dalam satu alur kerja yang lebih jelas.">
        <title>SupplierReady — Hubungan supplier, lebih terstruktur.</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="{{ asset('css/home.css') }}" rel="stylesheet">
    </head>
    <body>
        <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

        <div class="topline">
            <div class="container topline-inner">
                <span><span class="topline-dot"></span> Supplier management untuk hubungan bisnis yang bertumbuh.</span>
                <a href="#alur">Kenali alur SupplierReady <i class="bi bi-arrow-up-right"></i></a>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg site-nav sticky-top" aria-label="Navigasi utama">
            <div class="container">
                <a class="navbar-brand brand" href="#beranda" aria-label="SupplierReady, beranda">
                    <span class="brand-mark" aria-hidden="true"><i class="bi bi-layers-half"></i></span>
                    <span>supplier<span class="brand-ready">ready</span><span class="brand-period">.</span></span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama" aria-controls="menuUtama" aria-expanded="false" aria-label="Buka menu navigasi">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="menuUtama">
                    <ul class="navbar-nav mx-auto">
                        <li class="nav-item"><a class="nav-link" href="#solusi">Solusi</a></li>
                        <li class="nav-item"><a class="nav-link" href="#alur">Alur kerja</a></li>
                        <li class="nav-item"><a class="nav-link" href="#manfaat">Manfaat</a></li>
                        <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    </ul>
                    <a class="btn btn-blue nav-cta" href="#alur">Lihat cara kerjanya <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </nav>

        <main id="konten-utama">
            <section class="hero-section" id="beranda">
                <div class="hero-noise" aria-hidden="true"></div>
                <div class="container hero-container">
                    <div class="row align-items-center">
                        <div class="col-lg-6 hero-copy">
                            <div class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Satu ruang untuk ekosistem supplier</div>
                            <h1>Hubungan supplier,<br>lebih <span class="hero-highlight">terstruktur.</span></h1>
                            <p class="hero-description">Satukan profil supplier, dokumen, PO, pengiriman, invoice, dan pembayaran dalam satu alur kerja yang lebih mudah diikuti.</p>
                            <div class="hero-actions">
                                <a class="btn btn-mint" href="#alur">Jelajahi alur kerja <i class="bi bi-arrow-up-right"></i></a>
                                <a class="text-link-light" href="#manfaat"><span class="play-icon"><i class="bi bi-arrow-down"></i></span> Kenali manfaatnya</a>
                            </div>
                            <div class="hero-caption"><i class="bi bi-shield-check"></i> Dibangun untuk transaksi nyata, dengan akses yang bertanggung jawab.</div>
                        </div>

                        <div class="col-lg-6 hero-visual">
                            <div class="dashboard-wrap" data-reveal="float">
                                <div class="dash-back-orbit orbit-one"></div>
                                <div class="dash-back-orbit orbit-two"></div>
                                <article class="dashboard-card" aria-label="Ilustrasi tampilan ruang kerja SupplierReady">
                                    <div class="dashboard-topbar">
                                        <div class="dash-brand"><span class="dash-brand-icon"><i class="bi bi-layers-half"></i></span> Ruang kerja</div>
                                        <div class="dash-top-actions"><span class="dash-live"><span></span> Ringkasan alur</span><button type="button" class="dash-avatar" aria-label="Profil pengguna">P</button></div>
                                    </div>
                                    <div class="dashboard-body">
                                        <div class="dash-heading-row">
                                            <div><p class="dash-overline">SUPPLIER MANAGEMENT</p><h2>Ringkasan aktivitas</h2><p class="dash-subtitle">Selamat datang kembali di workspace Anda.</p></div>
                                            <button type="button" class="dash-date" aria-label="Periode saat ini"><i class="bi bi-calendar3"></i> Semua waktu</button>
                                        </div>

                                        <div class="dash-kpis">
                                            <div class="dash-kpi"><div class="kpi-top"><span>Supplier</span><span class="kpi-icon kpi-icon-green"><i class="bi bi-people"></i></span></div><strong>Ekosistem Anda</strong><small>Profil dan dokumen terpusat</small></div>
                                            <div class="dash-kpi"><div class="kpi-top"><span>Alur transaksi</span><span class="kpi-icon kpi-icon-blue"><i class="bi bi-arrow-left-right"></i></span></div><strong>Dari PO ke pembayaran</strong><small>Setiap tahap lebih terlihat</small></div>
                                        </div>

                                        <div class="dash-workflow-card">
                                            <div class="dash-workflow-head"><div><h3>Ikuti perjalanan transaksi</h3><span>Informasi setiap tahap dalam satu alur</span></div><i class="bi bi-three-dots"></i></div>
                                            <div class="dash-stage-tabs" role="group" aria-label="Pilih tahap transaksi">
                                                <button class="stage-pill is-active" type="button" data-workflow-stage="po" aria-pressed="true"><span>01</span> PO</button>
                                                <button class="stage-pill" type="button" data-workflow-stage="delivery" aria-pressed="false"><span>02</span> Pengiriman</button>
                                                <button class="stage-pill" type="button" data-workflow-stage="invoice" aria-pressed="false"><span>03</span> Invoice</button>
                                            </div>
                                            <div class="stage-detail" aria-live="polite">
                                                <div class="stage-detail-icon"><i class="bi bi-file-earmark-text"></i></div>
                                                <div class="stage-detail-copy"><strong data-stage-title>Purchase order</strong><span data-stage-copy>Permintaan, persetujuan, dan status pesanan terdokumentasi.</span></div>
                                                <span class="stage-detail-badge"><span></span> Tahap 01</span>
                                            </div>
                                            <div class="dash-progress"><span></span></div>
                                            <div class="dash-footnote"><i class="bi bi-lock"></i> Pratinjau tampilan · data contoh</div>
                                        </div>
                                    </div>
                                </article>
                                <div class="float-note note-verified"><span class="note-icon"><i class="bi bi-check2-circle"></i></span><span><strong>Data terkonfirmasi</strong><small>Berdasarkan alur transaksi</small></span></div>
                                <div class="float-note note-doc"><span class="note-icon note-icon-blue"><i class="bi bi-file-earmark-check"></i></span><span><strong>Siap ditinjau</strong><small>Dokumen supplier</small></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="hero-bottom">
                        <span>DIRANCANG UNTUK ALUR BISNIS B2B</span>
                        <div class="hero-tags"><span><i class="bi bi-building"></i> Perusahaan anchor</span><span><i class="bi bi-box-seam"></i> Supplier UMKM</span><span><i class="bi bi-bank"></i> Mitra pembiayaan</span></div>
                    </div>
                </div>
            </section>

            <section class="intro-strip" aria-label="Cakupan pengelolaan supplier">
                <div class="container">
                    <div class="intro-strip-inner">
                        <span class="intro-lead">Dari awal kerja sama hingga transaksi selesai.</span>
                        <div class="intro-process"><span>Profil</span><i class="bi bi-arrow-right"></i><span>Pesanan</span><i class="bi bi-arrow-right"></i><span>Pengiriman</span><i class="bi bi-arrow-right"></i><span>Pembayaran</span></div>
                    </div>
                </div>
            </section>

            <section class="section-pad problem-section" id="solusi">
                <div class="container">
                    <div class="section-heading text-center" data-reveal>
                        <span class="eyebrow"><span class="eyebrow-line"></span> Proses yang lebih jelas</span>
                        <h2>Urusan supplier tak seharusnya<br><span class="text-soft-blue">tercecer di mana-mana.</span></h2>
                        <p>Ketika dokumen, pesanan, dan pembayaran berpindah-pindah kanal, tim kehilangan waktu untuk mencari informasi, bukan menjalankan pekerjaan.</p>
                    </div>
                    <div class="row g-4 problem-cards">
                        <div class="col-md-4" data-reveal>
                            <article class="problem-card"><div class="problem-icon icon-coral"><i class="bi bi-folder2-open"></i></div><h3>Data terpisah-pisah</h3><p>Profil dan dokumen supplier berada di spreadsheet, email, folder, serta percakapan yang berbeda.</p><span class="card-index">01 / FRAGMENTASI</span></article>
                        </div>
                        <div class="col-md-4" data-reveal="delay-1">
                            <article class="problem-card"><div class="problem-icon icon-amber"><i class="bi bi-arrow-repeat"></i></div><h3>Pekerjaan berulang</h3><p>Pemeriksaan supplier dan pencatatan transaksi harus dilakukan manual, lalu diulang di beberapa tempat.</p><span class="card-index">02 / PROSES</span></article>
                        </div>
                        <div class="col-md-4" data-reveal="delay-2">
                            <article class="problem-card"><div class="problem-icon icon-blue"><i class="bi bi-eye"></i></div><h3>Status sulit ditelusuri</h3><p>Tim kesulitan melihat posisi pesanan, pengiriman, invoice, dan pembayaran dalam satu gambaran.</p><span class="card-index">03 / VISIBILITAS</span></article>
                        </div>
                    </div>
                    <div class="solution-callout" data-reveal><div class="callout-check"><i class="bi bi-check-lg"></i></div><p><strong>Satu alur yang saling terhubung.</strong> Informasi lebih mudah ditelusuri tanpa menggantikan sistem ERP yang sudah perusahaan gunakan.</p><a href="#alur">Lihat alurnya <i class="bi bi-arrow-right"></i></a></div>
                </div>
            </section>

            <section class="workflow-section section-pad" id="alur">
                <div class="container">
                    <div class="row align-items-end g-4 workflow-heading">
                        <div class="col-lg-7" data-reveal><span class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Bagaimana cara kerjanya</span><h2>Dari supplier bergabung<br>hingga transaksi tercatat.</h2></div>
                        <div class="col-lg-5" data-reveal><p>Satu rangkaian proses untuk membantu semua pihak mengikuti perkembangan hubungan bisnis dari tahap ke tahap.</p></div>
                    </div>

                    <div class="workflow-line" data-reveal>
                        <article class="workflow-step"><span class="step-number">01</span><div class="step-icon"><i class="bi bi-person-vcard"></i></div><span class="step-label">MULAI DARI PROFIL</span><h3>Supplier bergabung</h3><p>Supplier melengkapi profil dan dokumen melalui undangan dari perusahaan.</p><span class="step-tag"><i class="bi bi-building-check"></i> Profil & verifikasi</span></article>
                        <article class="workflow-step"><span class="step-number">02</span><div class="step-icon"><i class="bi bi-receipt"></i></div><span class="step-label">LANJUTKAN KE PESANAN</span><h3>PO dibuat dan dikonfirmasi</h3><p>Tim membuat pesanan pembelian; persetujuan mengikuti alur kerja perusahaan.</p><span class="step-tag"><i class="bi bi-file-earmark-text"></i> PO & persetujuan</span></article>
                        <article class="workflow-step"><span class="step-number">03</span><div class="step-icon"><i class="bi bi-box-seam"></i></div><span class="step-label">CATAT SERAH TERIMA</span><h3>Pengiriman diverifikasi</h3><p>Pengiriman dan penerimaan barang dicatat untuk melengkapi jejak transaksi.</p><span class="step-tag"><i class="bi bi-clipboard2-check"></i> Pengiriman & BAST</span></article>
                        <article class="workflow-step"><span class="step-number">04</span><div class="step-icon"><i class="bi bi-journal-check"></i></div><span class="step-label">TUTUP SIKLUS TRANSAKSI</span><h3>Invoice hingga pembayaran</h3><p>Invoice dan status pembayaran mengikuti transaksi yang telah dijalankan.</p><span class="step-tag"><i class="bi bi-credit-card-2-front"></i> Invoice & pembayaran</span></article>
                    </div>
                    <div class="workflow-note" data-reveal><i class="bi bi-info-circle"></i> Semua catatan berasal dari proses bisnis aktual; SupplierReady tidak membuat atau mengarang transaksi.</div>
                </div>
            </section>

            <section class="section-pad audience-section" id="manfaat">
                <div class="container">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-5" data-reveal>
                            <span class="eyebrow"><span class="eyebrow-line"></span> Manfaat untuk semua pihak</span>
                            <h2>Satu kerja sama.<br><span class="text-soft-blue">Perspektif yang berbeda.</span></h2>
                            <p class="audience-intro">Pilih peran untuk melihat bagaimana alur supplier yang lebih terstruktur membantu setiap pihak.</p>
                            <div class="nav audience-nav" id="stakeholder-tabs" role="tablist" aria-label="Pilih peran Anda">
                                <button class="audience-tab active" id="anchor-tab" data-bs-toggle="tab" data-bs-target="#anchor-pane" type="button" role="tab" aria-controls="anchor-pane" aria-selected="true"><span class="audience-tab-icon"><i class="bi bi-buildings"></i></span><span><strong>Perusahaan anchor</strong><small>Kelola supplier dalam satu ruang</small></span><i class="bi bi-arrow-right tab-arrow"></i></button>
                                <button class="audience-tab" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier-pane" type="button" role="tab" aria-controls="supplier-pane" aria-selected="false"><span class="audience-tab-icon"><i class="bi bi-box-seam"></i></span><span><strong>Supplier UMKM</strong><small>Ikuti alur kerja dan riwayat transaksi</small></span><i class="bi bi-arrow-right tab-arrow"></i></button>
                                <button class="audience-tab" id="partner-tab" data-bs-toggle="tab" data-bs-target="#partner-pane" type="button" role="tab" aria-controls="partner-pane" aria-selected="false"><span class="audience-tab-icon"><i class="bi bi-bank"></i></span><span><strong>Mitra pembiayaan</strong><small>Tinjau data dengan persetujuan</small></span><i class="bi bi-arrow-right tab-arrow"></i></button>
                            </div>
                        </div>
                        <div class="col-lg-7" data-reveal="delay-1">
                            <div class="tab-content audience-content">
                                <div class="tab-pane fade show active" id="anchor-pane" role="tabpanel" aria-labelledby="anchor-tab" tabindex="0">
                                    <div class="audience-detail"><div class="audience-art art-anchor"><div class="art-caption"><i class="bi bi-buildings"></i> PROCUREMENT WORKSPACE</div><div class="art-flow"><span>Supplier</span><i class="bi bi-arrow-right"></i><span>Purchase order</span><i class="bi bi-arrow-right"></i><span>Payment</span></div><div class="art-footer"><i class="bi bi-eye"></i> Status proses, mudah ditelusuri</div></div><span class="eyebrow"><span class="eyebrow-line"></span> UNTUK TIM PROCUREMENT</span><h3>Lebih banyak kendali. Lebih sedikit mencari.</h3><p>Kelola data dan dokumen supplier di satu tempat. Pantau status pesanan sampai pembayaran, dengan visibilitas yang lebih konsisten untuk tim.</p><ul class="benefit-list"><li><i class="bi bi-check-circle-fill"></i> Onboarding supplier yang lebih tertata</li><li><i class="bi bi-check-circle-fill"></i> Status PO, pengiriman, dan invoice lebih mudah dipantau</li><li><i class="bi bi-check-circle-fill"></i> Bisa melengkapi sistem perusahaan yang sudah ada</li></ul></div>
                                </div>
                                <div class="tab-pane fade" id="supplier-pane" role="tabpanel" aria-labelledby="supplier-tab" tabindex="0">
                                    <div class="audience-detail"><div class="audience-art art-supplier"><div class="art-caption"><i class="bi bi-box-seam"></i> PUSAT AKTIVITAS SUPPLIER</div><div class="supplier-document"><i class="bi bi-file-earmark-check"></i><span><strong>Transaksi tercatat</strong><small>Dokumen mengikuti alur kerja</small></span><i class="bi bi-arrow-up-right"></i></div><div class="supplier-document"><i class="bi bi-truck"></i><span><strong>Pengiriman terlacak</strong><small>Konfirmasi dari kedua pihak</small></span><i class="bi bi-arrow-up-right"></i></div><div class="art-footer"><i class="bi bi-person-check"></i> Akses data atas persetujuan</div></div><span class="eyebrow"><span class="eyebrow-line"></span> UNTUK SUPPLIER UMKM</span><h3>Riwayat transaksi, lebih mudah ditelusuri.</h3><p>Lengkapi profil, ikuti pesanan, dan pantau invoice dalam alur yang sama dengan perusahaan yang mengundang Anda.</p><ul class="benefit-list"><li><i class="bi bi-check-circle-fill"></i> Profil dan dokumen lebih terstruktur</li><li><i class="bi bi-check-circle-fill"></i> Status pesanan dan pembayaran lebih terlihat</li><li><i class="bi bi-check-circle-fill"></i> Anda mengendalikan persetujuan berbagi data</li></ul></div>
                                </div>
                                <div class="tab-pane fade" id="partner-pane" role="tabpanel" aria-labelledby="partner-tab" tabindex="0">
                                    <div class="audience-detail"><div class="audience-art art-partner"><div class="art-caption"><i class="bi bi-bank"></i> SHARED WITH CONSENT</div><div class="partner-data"><span><i class="bi bi-file-earmark-text"></i> Invoice terkonfirmasi</span><span><i class="bi bi-box-seam"></i> Catatan pengiriman</span><span><i class="bi bi-check-circle"></i> Persetujuan supplier</span></div><div class="art-footer"><i class="bi bi-shield-check"></i> Evaluasi independen oleh mitra</div></div><span class="eyebrow"><span class="eyebrow-line"></span> UNTUK MITRA PEMBIAYAAN</span><h3>Konteks transaksi yang lebih mudah ditinjau.</h3><p>Dengan izin supplier, data transaksi yang terstruktur dapat mendukung proses evaluasi independen oleh mitra berizin.</p><ul class="benefit-list"><li><i class="bi bi-check-circle-fill"></i> Data bersumber dari transaksi aktual</li><li><i class="bi bi-check-circle-fill"></i> Informasi hanya dibagikan dengan persetujuan</li><li><i class="bi bi-check-circle-fill"></i> Evaluasi dan keputusan tetap milik mitra</li></ul></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="capabilities-section section-pad" id="fitur">
                <div class="container">
                    <div class="section-heading text-center" data-reveal><span class="eyebrow"><span class="eyebrow-line"></span> Dirancang untuk operasional harian</span><h2>Semua tahap penting,<br><span class="text-soft-blue">dalam satu pandangan.</span></h2><p>Fondasi yang relevan untuk memulai alur supplier management dan berkembang bersama kebutuhan bisnis.</p></div>
                    <div class="row g-3 feature-grid">
                        <div class="col-md-6 col-lg-3" data-reveal><article class="feature-card"><div class="feature-icon feature-green"><i class="bi bi-person-vcard"></i></div><span class="feature-number">01</span><h3>Profil supplier</h3><p>Informasi supplier dan dokumen pendukung tertata dalam satu profil.</p><a href="#alur" aria-label="Pelajari lebih lanjut tentang profil supplier"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-1"><article class="feature-card"><div class="feature-icon feature-blue"><i class="bi bi-receipt"></i></div><span class="feature-number">02</span><h3>Pelacakan PO</h3><p>Proses pesanan dan konfirmasi lebih mudah diikuti dari awal.</p><a href="#alur" aria-label="Pelajari lebih lanjut tentang pelacakan PO"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-2"><article class="feature-card"><div class="feature-icon feature-amber"><i class="bi bi-truck"></i></div><span class="feature-number">03</span><h3>Konfirmasi pengiriman</h3><p>Catatan pengiriman dan penerimaan barang di alur yang sama.</p><a href="#alur" aria-label="Pelajari lebih lanjut tentang konfirmasi pengiriman"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-3"><article class="feature-card"><div class="feature-icon feature-purple"><i class="bi bi-journal-check"></i></div><span class="feature-number">04</span><h3>Invoice & pembayaran</h3><p>Status invoice dan pembayarannya lebih transparan bagi pihak terkait.</p><a href="#alur" aria-label="Pelajari lebih lanjut tentang invoice dan pembayaran"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal><article class="feature-card"><div class="feature-icon feature-blue"><i class="bi bi-graph-up-arrow"></i></div><span class="feature-number">05</span><h3>Visibilitas kinerja</h3><p>Gunakan catatan operasional untuk meninjau hubungan supplier.</p><a href="#manfaat" aria-label="Pelajari lebih lanjut tentang visibilitas kinerja"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-1"><article class="feature-card"><div class="feature-icon feature-green"><i class="bi bi-shield-lock"></i></div><span class="feature-number">06</span><h3>Kontrol akses</h3><p>Dukung tata kelola melalui peran pengguna dan persetujuan data.</p><a href="#manfaat" aria-label="Pelajari lebih lanjut tentang kontrol akses"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-2"><article class="feature-card"><div class="feature-icon feature-purple"><i class="bi bi-clock-history"></i></div><span class="feature-number">07</span><h3>Riwayat transaksi</h3><p>Rekam jejak terbentuk dari proses bisnis yang benar-benar dijalankan.</p><a href="#manfaat" aria-label="Pelajari lebih lanjut tentang riwayat transaksi"><i class="bi bi-arrow-up-right"></i></a></article></div>
                        <div class="col-md-6 col-lg-3" data-reveal="delay-3"><article class="feature-card"><div class="feature-icon feature-amber"><i class="bi bi-arrow-left-right"></i></div><span class="feature-number">08</span><h3>Siap berdampingan</h3><p>Lapisan alur kerja yang dapat bekerja bersama ERP perusahaan.</p><a href="#solusi" aria-label="Pelajari lebih lanjut tentang integrasi alur kerja"><i class="bi bi-arrow-up-right"></i></a></article></div>
                    </div>
                </div>
            </section>

            <section class="responsibility-section">
                <div class="container">
                    <div class="responsibility-card" data-reveal>
                        <div class="responsibility-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="responsibility-copy"><span class="eyebrow eyebrow-light">Data yang terpercaya dimulai dari proses yang jujur</span><h2>Transaksi tetap nyata.<br>Keputusan tetap milik Anda.</h2><p>SupplierReady membantu menata alur dan catatan transaksi. Berbagi informasi selalu membutuhkan persetujuan; keputusan pengadaan dan pembiayaan tetap berada pada pihak yang berwenang.</p><a href="#faq" class="responsibility-link">Pelajari pendekatan kami <i class="bi bi-arrow-right"></i></a></div>
                        <div class="responsibility-orb" aria-hidden="true"><i class="bi bi-shield-lock"></i></div>
                    </div>
                </div>
            </section>

            <section class="section-pad faq-section" id="faq">
                <div class="container">
                    <div class="row g-4">
                        <div class="col-lg-5" data-reveal><span class="eyebrow"><span class="eyebrow-line"></span> Pertanyaan umum</span><h2>Beberapa hal yang<br>penting untuk diketahui.</h2><p class="faq-intro">Memahami peran SupplierReady adalah langkah awal membangun kolaborasi supplier yang lebih baik.</p><a class="faq-contact" href="#kontak">Lihat ringkasan <i class="bi bi-arrow-down-right"></i></a></div>
                        <div class="col-lg-7" data-reveal="delay-1">
                            <div class="accordion faq-accordion" id="faqList">
                                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne" aria-expanded="true" aria-controls="faqOne">Apakah SupplierReady menggantikan ERP perusahaan?</button></h3><div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#faqList"><div class="accordion-body">Tidak. SupplierReady berfokus pada alur kerja, visibilitas, dan pencatatan transaksi supplier. Platform ini dirancang untuk dapat melengkapi sistem yang telah digunakan perusahaan.</div></div></div>
                                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo" aria-expanded="false" aria-controls="faqTwo">Siapa pelanggan utama SupplierReady?</button></h3><div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#faqList"><div class="accordion-body">Perusahaan menengah atau besar dengan banyak supplier merupakan pelanggan utama dan pemilik workspace. Supplier UMKM yang diundang menggunakannya sebagai bagian dari alur kerja tersebut.</div></div></div>
                                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree" aria-expanded="false" aria-controls="faqThree">Apakah SupplierReady memberikan pinjaman atau menjamin persetujuan?</button></h3><div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#faqList"><div class="accordion-body">Tidak. SupplierReady bukan pemberi pinjaman, tidak menetapkan keputusan kredit, dan tidak menjamin persetujuan maupun pencairan dana. Evaluasi pembiayaan dilakukan secara independen oleh mitra yang berwenang.</div></div></div>
                                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour" aria-expanded="false" aria-controls="faqFour">Kapan informasi supplier dapat dibagikan kepada mitra?</button></h3><div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#faqList"><div class="accordion-body">Informasi transaksi hanya dapat dibagikan untuk keperluan yang relevan sesuai persetujuan pihak terkait, termasuk persetujuan supplier saat data akan digunakan dalam proses mitra pembiayaan.</div></div></div>
                                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFive" aria-expanded="false" aria-controls="faqFive">Apakah semua supplier harus memiliki tim teknologi?</button></h3><div id="faqFive" class="accordion-collapse collapse" data-bs-parent="#faqList"><div class="accordion-body">Tidak. Supplier bergabung melalui undangan perusahaan dan melengkapi informasi untuk kebutuhan kerja sama. Platform dirancang untuk membuat tahap-tahap tersebut lebih mudah diikuti.</div></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="closing-section" id="kontak">
                <div class="container">
                    <div class="closing-card" data-reveal>
                        <div class="closing-copy"><span class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Langkah berikutnya</span><h2>Mulai dari alur yang<br>paling Anda butuhkan.</h2><p>Kenali bagaimana SupplierReady dapat membantu perusahaan dan jaringan supplier Anda bekerja dengan lebih terstruktur.</p></div>
                        <div class="closing-action"><a class="btn btn-mint" href="#alur">Jelajahi alur kerja <i class="bi bi-arrow-up-right"></i></a><a class="closing-secondary" href="#solusi">Kembali ke gambaran solusi <i class="bi bi-arrow-right"></i></a><div class="closing-note"><i class="bi bi-chat-square-text"></i> Satu langkah pada satu waktu. Mulai dari proses yang sudah Anda jalankan.</div></div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <div class="container">
                <div class="footer-main">
                    <a class="brand footer-brand" href="#beranda" aria-label="SupplierReady, kembali ke beranda"><span class="brand-mark" aria-hidden="true"><i class="bi bi-layers-half"></i></span><span>supplier<span class="brand-ready">ready</span><span class="brand-period">.</span></span></a>
                    <p>Ruang kerja yang membantu perusahaan dan supplier UMKM melangkah bersama.</p>
                    <nav class="footer-links" aria-label="Navigasi footer"><a href="#solusi">Solusi</a><a href="#alur">Alur kerja</a><a href="#manfaat">Manfaat</a><a href="#faq">FAQ</a></nav>
                </div>
                <div class="footer-bottom"><span>&copy; {{ date('Y') }} SupplierReady. Semua hak dilindungi.</span><span>Catatan transaksi yang relevan. Dibagikan dengan persetujuan.</span><a href="#beranda" class="back-to-top">Kembali ke atas <i class="bi bi-arrow-up"></i></a></div>
            </div>
        </footer>

        <button class="back-top-button" type="button" aria-label="Kembali ke bagian atas"><i class="bi bi-arrow-up"></i></button>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('js/home.js') }}" defer></script>
    </body>
</html>
