<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta-description', 'Masuk atau buat akun Navora untuk menjelajahi marketplace kemasan UMKM.')">
    <title>@yield('title') — Navora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
    <link href="{{ asset('css/advertisements.css') }}" rel="stylesheet">
</head>
<body class="navora-auth-body" data-rajaongkir-locations-url="{{ route('rajaongkir.locations') }}">
    <main class="auth-layout">
        <section class="auth-panel">
            <header class="auth-header">
                <a class="auth-brand" href="{{ route('home') }}" aria-label="Navora beranda">
                    <span class="auth-brand-icon"><i class="bi bi-box-seam"></i></span>
                    <span>Navora<span class="auth-brand-dot">.</span></span>
                </a>
                <a class="auth-back-link" href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> Beranda</a>
            </header>
            @include('advertisements.banner')
            <div class="auth-form-wrap @yield('auth-form-class')">
                @yield('auth-form')
            </div>
            <footer class="auth-footer">
                <span>© {{ date('Y') }} Navora</span>
                <span>Marketplace kemasan untuk ekosistem UMKM</span>
            </footer>
        </section>
        <aside class="auth-aside">
            <div class="auth-aside-decoration auth-orb-one"></div>
            <div class="auth-aside-decoration auth-orb-two"></div>
            <div class="auth-aside-content">
                <span class="auth-aside-kicker"><span></span> TUMBUH BERSAMA NAVORA</span>
                <h1>Ide produkmu pantas punya <em>kemasan terbaik.</em></h1>
                <p>Temukan vendor, bandingkan kebutuhan, dan jelajahi cara baru memenuhi MOQ dalam satu ruang.</p>
                <div class="auth-aside-steps">
                    <span><i class="bi bi-search"></i> Cari vendor yang sesuai</span>
                    <span><i class="bi bi-people"></i> Jelajahi peluang patungan</span>
                    <span><i class="bi bi-chat-square-text"></i> Bandingkan penawaran</span>
                </div>
                <div class="auth-aside-note"><i class="bi bi-info-circle"></i> Prototype Navora · Fitur marketplace saat ini masih dalam tahap demo.</div>
            </div>
            <div class="auth-aside-mark"><i class="bi bi-box-seam"></i><span>NAVORA</span></div>
        </aside>
    </main>
    @if (config('services.midtrans.client_key'))
        <script src="{{ config('services.midtrans.base_url') }}/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif
    <script src="{{ asset('js/rajaongkir-location.js').'?v='.filemtime(public_path('js/rajaongkir-location.js')) }}" defer></script>
    <script src="{{ asset('js/vendor-subscription.js').'?v='.filemtime(public_path('js/vendor-subscription.js')) }}" defer></script>
</body>
</html>
