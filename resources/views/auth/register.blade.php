@extends('auth.partials.layout')

@section('title', 'Buat akun')
@section('meta-description', 'Buat akun Navora untuk mulai menjelajahi vendor kemasan dan peluang kolaborasi bagi UMKM.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> MULAI LANGKAH BARUMU</span>
        <h2>Buat akun Navora</h2>
        <p>Daftar untuk terhubung dengan ekosistem kemasan UMKM.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Periksa kembali data pendaftaranmu.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form action="{{ route('register.store') }}" method="post" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="role">Saya bergabung sebagai</label>
            <div class="auth-input-wrap auth-select-wrap">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <select id="role" name="role" required aria-describedby="@error('role') role-error @enderror">
                    <option value="umkm" @selected(old('role', request('role', 'umkm')) === 'umkm')>Pemilik brand / UMKM</option>
                    <option value="vendor" @selected(old('role', request('role')) === 'vendor')>Vendor kemasan atau maklon</option>
                    <option value="partner" @selected(old('role', request('role')) === 'partner')>Mitra pendukung UMKM</option>
                </select>
                <i class="bi bi-chevron-down auth-select-chevron" aria-hidden="true"></i>
            </div>
            @error('role')<span class="auth-field-error" id="role-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="name">Nama lengkap atau nama usaha</label>
            <div class="auth-input-wrap">
                <i class="bi bi-person" aria-hidden="true"></i>
                <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Contoh: Dinda / Kopi Nusantara" autocomplete="name" maxlength="255" required autofocus aria-describedby="@error('name') name-error @enderror">
            </div>
            @error('name')<span class="auth-field-error" id="name-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="email">Email aktif</label>
            <div class="auth-input-wrap">
                <i class="bi bi-envelope" aria-hidden="true"></i>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@contoh.com" autocomplete="email" maxlength="255" required aria-describedby="@error('email') email-error @enderror">
            </div>
            @error('email')<span class="auth-field-error" id="email-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="password">Buat kata sandi</label>
            <div class="auth-input-wrap">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input id="password" name="password" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password" minlength="8" required aria-describedby="@error('password') password-error @enderror">
            </div>
            @error('password')<span class="auth-field-error" id="password-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="password_confirmation">Ulangi kata sandi</label>
            <div class="auth-input-wrap">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Ketik ulang kata sandi" autocomplete="new-password" minlength="8" required>
            </div>
        </div>
        <p class="auth-privacy-note"><i class="bi bi-shield-check"></i> Gunakan kata sandi yang kuat dan jangan bagikan kepada siapa pun.</p>
        <button class="auth-submit" type="submit">Buat akun <i class="bi bi-arrow-right"></i></button>
    </form>

    <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
@endsection
