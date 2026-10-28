@extends('auth.partials.layout')

@section('title', 'Masuk')
@section('meta-description', 'Masuk ke akun Navora untuk melanjutkan menjelajahi vendor kemasan dan peluang kolaborasi UMKM.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> SENANG MELIHATMU LAGI</span>
        <h2>Masuk ke Navora</h2>
        <p>Gunakan email dan kata sandi yang terdaftar untuk melanjutkan.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Belum bisa masuk.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form action="{{ route('login.store') }}" method="post" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="email">Email</label>
            <div class="auth-input-wrap">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@contoh.com" autocomplete="email" required autofocus aria-describedby="@error('email') email-error @enderror">
            </div>
            @error('email')<span class="auth-field-error" id="email-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <div class="auth-label-row"><label for="password">Kata sandi</label><span>Minimal 8 karakter</span></div>
            <div class="auth-input-wrap">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input id="password" name="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
            </div>
            @error('password')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <label class="auth-remember"><input name="remember" type="checkbox" value="1"><span>Ingat saya di perangkat ini</span></label>
        <button class="auth-submit" type="submit">Masuk <i class="bi bi-arrow-right"></i></button>
    </form>

    <p class="auth-switch">Belum punya akun? <a href="{{ route('register') }}">Daftar gratis</a></p>
    <p class="auth-security-note"><i class="bi bi-shield-lock"></i> Kata sandi disimpan dalam bentuk hash dan tidak bisa dibaca langsung.</p>
@endsection
