<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('meta-description', 'Dashboard admin Navora.')">
    <title>@yield('title') — Admin Navora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    <link href="{{ asset('css/advertisements.css') }}" rel="stylesheet">
</head>
<body class="navora-admin-body">
    <header class="navora-admin-header">
        <div class="navora-admin-top">
            <a class="navora-admin-brand" href="{{ route('admin.dashboard') }}" aria-label="Navora, dashboard admin">
                <span class="auth-brand-icon"><i class="bi bi-box-seam"></i></span>
                <span>Navora<span class="auth-brand-dot">.</span><small>ADMIN</small></span>
            </a>
            <div class="navora-admin-account">
                <span><strong>{{ Auth::user()->name }}</strong><small>Administrator</small></span>
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="navora-admin-logout" type="submit" aria-label="Keluar dari akun admin"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </div>
        <nav class="navora-admin-nav" aria-label="Navigasi admin">
            <a href="{{ route('admin.dashboard') }}" @class(['is-active' => request()->routeIs('admin.dashboard')])>
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="{{ route('admin.vendor-verifications.index') }}" @class(['is-active' => request()->routeIs('admin.vendor-verifications.*')])>
                <i class="bi bi-patch-check"></i> Verifikasi vendor
            </a>
            <a href="{{ route('admin.customize.index') }}" @class(['is-active' => request()->routeIs('admin.customize.*')])>
                <i class="bi bi-sliders"></i> Customize
            </a>
            <a href="{{ route('admin.feedback.index') }}" @class(['is-active' => request()->routeIs('admin.feedback.*')])>
                <i class="bi bi-chat-square-text"></i> Masukan
            </a>
        </nav>
    </header>

    <main class="navora-admin-main">
        @yield('admin-content')
    </main>
</body>
</html>
