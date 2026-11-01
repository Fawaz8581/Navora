@extends('admin.layout')

@section('title', 'Iklan')
@section('meta-description', 'Tinjau pembayaran Midtrans, pratinjau banner, dan kelola iklan vendor Navora.')

@section('admin-content')
    <section class="admin-welcome">
        <div>
            <span class="admin-eyebrow">IKLAN</span>
            <h1>Iklan dan pendapatan Navora</h1>
            <p>Kelola paket iklan, tinjau pembayaran Midtrans, putuskan iklan dengan alasan, dan pantau pemasukan.</p>
        </div>
        <span class="admin-welcome-icon"><i class="bi bi-megaphone"></i></span>
    </section>

    @if (session('status'))
        <div class="admin-notice" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-notice admin-notice-error" role="alert">
            <strong>Periksa kembali data berikut.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="admin-panel admin-customize-section">
        <div class="admin-panel-heading">
            <div><span class="admin-eyebrow">PEMASUKAN</span><h2>Pendapatan iklan</h2></div>
        </div>
        <p class="admin-ad-income">Rp{{ number_format($verifiedIncome, 0, ',', '.') }}</p>
        <p class="admin-customize-copy">Total pembayaran iklan yang terverifikasi dan belum tercatat sebagai refund selesai.</p>
    </section>

    <section class="admin-panel admin-customize-section">
        <div class="admin-panel-heading">
            <div><span class="admin-eyebrow">PEMBAYARAN DAN ASET</span><h2>Antrean iklan</h2></div>
        </div>
        @forelse ($campaigns as $campaign)
            @php
                $statusLabels = [
                    'pending_payment' => 'Menunggu pembayaran',
                    'payment_failed' => 'Pembayaran belum berhasil',
                    'paid' => 'Lunas · menunggu aset',
                    'awaiting_asset' => 'Disetujui · menunggu aset vendor',
                    'asset_submitted' => 'Lunas · menunggu tinjauan admin',
                    'active' => 'Tayang',
                    'rejected' => 'Ditolak',
                ];
                $paymentStatusLabels = [
                    'pending' => 'Menunggu pembayaran Midtrans',
                    'paid' => 'Lunas',
                    'settlement' => 'Berhasil',
                    'capture' => 'Berhasil',
                    'expire' => 'Kedaluwarsa',
                    'cancel' => 'Dibatalkan',
                    'cancelled' => 'Dibatalkan',
                    'failure' => 'Gagal',
                    'deny' => 'Ditolak Midtrans',
                    'fraud_denied' => 'Ditolak pemeriksaan risiko Midtrans',
                    'refunded' => 'Dana dikembalikan',
                    'partially_refunded' => 'Dana dikembalikan sebagian',
                    'refund' => 'Dana dikembalikan',
                    'partial_refund' => 'Dana dikembalikan sebagian',
                    'initiation_failed' => 'Checkout gagal dibuat',
                ];
                $refundLabels = [
                    'not_requested' => 'Tidak ada refund',
                    'pending' => 'Refund sedang diajukan',
                    'requested' => 'Permintaan refund diterima Midtrans',
                    'refunded' => 'Refund selesai',
                    'partially_refunded' => 'Refund sebagian',
                    'cancel_requested' => 'Pembatalan transaksi diterima Midtrans',
                    'cancelled' => 'Pembayaran berhasil dibatalkan',
                    'failed' => 'Refund gagal',
                ];
            @endphp
            <article class="admin-ad-campaign">
                <div class="admin-ad-campaign-heading">
                    <div><strong>{{ $campaign->ad_title ?: $campaign->package_name }}</strong><span>{{ $campaign->vendor->name }} · {{ $campaign->vendor->email }}</span></div>
                    <span class="admin-status-pill">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span>
                </div>
                <p>{{ $campaign->placement === 'sidebar' ? 'Slot samping' : 'Banner atas' }} · {{ $campaign->width }} × {{ $campaign->height }} px · {{ $campaign->duration_days }} hari · Rp{{ number_format($campaign->amount, 0, ',', '.') }} · Status pembayaran: {{ $paymentStatusLabels[$campaign->payment_status] ?? $campaign->payment_status }}</p>
                @if ($campaign->paid_at)
                    <div class="admin-ad-payment-proof">
                        <strong>Bukti pembayaran Midtrans</strong>
                        <span>Order ID: {{ $campaign->order_id }}</span>
                        <span>ID transaksi: {{ $campaign->midtrans_transaction_id ?: 'Tidak dikirim Midtrans' }}</span>
                        <span>Metode: {{ $campaign->payment_type ?: '—' }} · Dibayar {{ $campaign->paid_at->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if ($campaign->destination_url)
                    <p>Tautan tujuan: <a href="{{ $campaign->destination_url }}" target="_blank" rel="noopener noreferrer">{{ $campaign->destination_url }} <i class="bi bi-box-arrow-up-right"></i></a></p>
                @endif
                <small>Dibuat {{ $campaign->created_at->format('d M Y H:i') }}</small>
                @if ($campaign->review_reason)
                    <p class="admin-ad-review-reason"><strong>Alasan tinjauan:</strong> {{ $campaign->review_reason }}</p>
                @endif
                @if ($campaign->refund_status !== 'not_requested')
                    <p>Status refund: {{ $refundLabels[$campaign->refund_status] ?? $campaign->refund_status }}</p>
                @endif
                @if ($campaign->status === 'asset_submitted' && $campaign->payment_status === 'paid')
                    <div class="admin-ad-preview">
                        <a href="{{ $campaign->destination_url }}" target="_blank" rel="noopener noreferrer" aria-label="Buka tautan tujuan iklan {{ $campaign->ad_title ?: $campaign->package_name }}">
                            <img src="{{ route('admin.customize.campaigns.asset', $campaign) }}" alt="Pratinjau banner {{ $campaign->ad_title ?: $campaign->package_name }}">
                        </a>
                        <span>Pratinjau klik · membuka tautan tujuan di tab baru</span>
                    </div>
                    <form class="admin-ad-review-form" action="{{ route('admin.customize.campaigns.review', $campaign) }}" method="post">
                        @csrf
                        <label for="review-reason-{{ $campaign->id }}">Catatan keputusan (wajib diisi jika menolak)</label>
                        <textarea id="review-reason-{{ $campaign->id }}" name="reason" maxlength="255" rows="2"></textarea>
                        <div class="admin-ad-actions">
                            <button class="admin-table-action" type="submit" name="decision" value="approve">Terima &amp; tayangkan</button>
                            <button class="admin-table-action admin-ad-reject-action" type="submit" name="decision" value="reject">Tolak &amp; ajukan refund</button>
                        </div>
                    </form>
                @elseif ($campaign->status === 'asset_submitted')
                    <p>Pembayaran belum terverifikasi; keputusan iklan dinonaktifkan sampai notifikasi Midtrans diterima.</p>
                @elseif ($campaign->status === 'pending_payment' && $campaign->snap_token)
                    <form class="admin-ad-refresh-form" action="{{ route('vendor.advertisements.refresh', $campaign) }}" method="post">
                        @csrf
                        <button class="admin-table-action" type="submit">Periksa ulang status pembayaran</button>
                    </form>
                @elseif ($campaign->status === 'active')
                    <p>Tayang hingga {{ $campaign->ends_at?->format('d M Y H:i') }}.</p>
                @elseif ($campaign->status === 'rejected' && $campaign->refund_status === 'failed')
                    <form action="{{ route('admin.customize.campaigns.refund.retry', $campaign) }}" method="post">
                        @csrf
                        <button class="admin-table-action" type="submit">Coba ajukan refund lagi</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="admin-empty-state"><i class="bi bi-hourglass-split"></i><strong>Belum ada campaign iklan</strong><span>Pemesanan vendor akan muncul di sini.</span></div>
        @endforelse
        <div class="admin-pagination">{{ $campaigns->links() }}</div>
    </section>
@endsection
