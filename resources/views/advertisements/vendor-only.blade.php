@extends('auth.partials.layout')

@section('title', 'Iklan khusus vendor')
@section('meta-description', 'Informasi akses iklan Navora untuk pengguna UMKM.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> INFORMASI IKLAN</span>
        <h2>Iklan hanya khusus vendor untuk saat ini</h2>
        <p>Konten promosi ini ditujukan untuk vendor Navora. Kamu tetap dapat kembali menjelajahi ruang kerja.</p>
    </div>
    <div class="ad-page-navigation" aria-label="Navigasi">
        <a href="{{ route('home') }}"><i class="bi bi-house"></i> Beranda</a>
        <a href="{{ route('workspace') }}"><i class="bi bi-grid-1x2"></i> Ruang kerja</a>
    </div>
@endsection
