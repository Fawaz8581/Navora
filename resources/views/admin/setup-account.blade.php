@extends('auth.partials.layout')

@section('title', 'Buat akun admin')
@section('meta-description', 'Buat akun admin Navora untuk lingkungan lokal.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> ADMIN NAVORA</span>
        <h2>{{ $wasCreated ? 'Admin account successfully created' : 'Admin account already created' }}</h2>
        <p>
            @if ($wasCreated)
                Akun admin <strong>{{ $email }}</strong> berhasil dibuat. Silakan masuk menggunakan halaman login biasa.
            @else
                Akun admin <strong>{{ $email }}</strong> sudah tersedia. Tidak ada akun baru yang dibuat.
            @endif
        </p>
    </div>
    <a class="auth-submit" href="{{ route('login') }}">Ke halaman login <i class="bi bi-arrow-right"></i></a>
@endsection
