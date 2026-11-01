@extends('auth.partials.layout')

@section('title', 'Iklan vendor')
@section('meta-description', 'Pesan iklan Navora dan pantau status pembayaran, aset, serta penayangannya.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> IKLAN VENDOR</span>
        <h2>Promosikan usahamu di Navora</h2>
        <p>Pilih paket, selesaikan pembayaran Midtrans, lalu kirim aset gambar atau GIF setelah campaign disetujui admin.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div><strong>Permintaan belum dapat diproses.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif
    @unless ($midtransConfigured)
        <div class="auth-alert auth-alert-info" role="status">
            <i class="bi bi-info-circle"></i>
            <span>Pembayaran iklan sementara belum tersedia. Silakan hubungi admin Navora.</span>
        </div>
    @endunless

    <section class="onboarding-review-section">
        <h3>Paket iklan tersedia</h3>
        @forelse ($packages as $package)
            <article class="ad-package-card">
                <div>
                    <strong>{{ $package->name }}</strong>
                    <p>{{ $package->placement === 'sidebar' ? 'Slot samping' : 'Banner atas' }} · {{ $package->width }} × {{ $package->height }} px · {{ $package->duration_days }} hari · Tayang di beranda dan ruang kerja</p>
                    <strong>Rp{{ number_format($package->price, 0, ',', '.') }}</strong>
                </div>
                <form action="{{ route('vendor.advertisements.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="ad_package_id" value="{{ $package->id }}">
                    <button class="auth-submit" type="submit" @disabled(! $midtransConfigured)>
                        {{ $midtransConfigured ? 'Pilih & bayar' : 'Pembayaran belum tersedia' }}
                        @if ($midtransConfigured)<i class="bi bi-arrow-right"></i>@endif
                    </button>
                </form>
            </article>
        @empty
            <p>Belum ada paket iklan yang tersedia. Silakan hubungi admin Navora.</p>
        @endforelse
    </section>

    <section class="onboarding-review-section">
        <h3>Status campaign iklan</h3>
        @forelse ($campaigns as $campaign)
            @php
                $statusLabels = [
                    'pending_payment' => 'Menunggu pembayaran',
                    'payment_failed' => 'Pembayaran belum berhasil',
                    'paid' => 'Pembayaran diterima · menunggu persetujuan',
                    'awaiting_asset' => 'Disetujui · menunggu aset',
                    'asset_submitted' => 'Aset dikirim · menunggu pemeriksaan',
                    'active' => 'Tayang',
                ];
            @endphp
            <article class="ad-campaign-card">
                <div>
                    <strong>{{ $campaign->package_name }}</strong>
                    <p>{{ $campaign->placement === 'sidebar' ? 'Slot samping' : 'Banner atas' }} · {{ $campaign->width }} × {{ $campaign->height }} px · {{ $campaign->duration_days }} hari · Rp{{ number_format($campaign->amount, 0, ',', '.') }}</p>
                    <span class="ad-campaign-status">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span>
                    @if ($campaign->status === 'active')
                        <p>Tayang hingga {{ $campaign->ends_at?->format('d M Y H:i') }}.</p>
                    @endif
                </div>
                @if ($campaign->status === 'awaiting_asset')
                    <form action="{{ route('vendor.advertisements.asset', $campaign) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <label class="auth-field" for="ad-asset-{{ $campaign->id }}">
                            Aset gambar atau GIF (maks. 5 MB)
                            <input id="ad-asset-{{ $campaign->id }}" name="asset" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
                        </label>
                        <button class="auth-submit" type="submit">Kirim aset untuk ditinjau <i class="bi bi-send"></i></button>
                    </form>
                @endif
            </article>
        @empty
            <p>Belum ada campaign iklan. Pilih paket di atas untuk memulai.</p>
        @endforelse
    </section>

    <p class="auth-switch"><a href="{{ route('workspace') }}">Kembali ke ruang Navora</a></p>
@endsection
