@extends('auth.partials.layout')

@section('title', 'Iklan vendor')
@section('meta-description', 'Pesan iklan Navora dan pantau status pembayaran, aset, serta penayangannya.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> IKLAN VENDOR</span>
        <h2>Promosikan usahamu di Navora</h2>
        <p>Isi informasi iklan dan unggah banner PNG atau GIF. Setelah pembayaran Midtrans terverifikasi, admin akan meninjau iklanmu. Jika disetujui, iklan langsung tayang sesuai posisi yang dipilih.</p>
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
        <h3>Form dan harga iklan</h3>
        <p>Tarif banner atas Rp200.000 dan banner samping Rp100.000. Ukuran banner atas rekomendasi 1200 × 300 px; maksimal 5 MB.</p>
        @if ($packages->isNotEmpty())
            <form class="ad-campaign-form" action="{{ route('vendor.advertisements.store') }}" method="post" enctype="multipart/form-data" data-ad-checkout-form>
                @csrf
                <label class="auth-field" for="ad-package">Posisi dan durasi</label>
                <select id="ad-package" name="ad_package_id" required>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected(old('ad_package_id', $packages->first()->id) == $package->id)>
                            {{ $package->placement === 'sidebar' ? 'Slot samping · Rp100.000' : 'Banner atas · Rp200.000' }} · {{ $package->width }} × {{ $package->height }} px · {{ $package->duration_days }} hari
                        </option>
                    @endforeach
                </select>
                <label class="auth-field" for="ad-title">Judul iklan</label>
                <input id="ad-title" name="ad_title" type="text" maxlength="120" value="{{ old('ad_title') }}" placeholder="Contoh: Kemasan premium untuk produkmu" required>
                <label class="auth-field" for="ad-destination-url">Tautan tujuan iklan (HTTPS)</label>
                <input id="ad-destination-url" name="destination_url" type="url" value="{{ old('destination_url') }}" placeholder="https://contoh.com/promo" required>
                <label class="auth-field" for="ad-asset">Banner iklan (PNG atau GIF, maks. 5 MB)</label>
                <input id="ad-asset" name="asset" type="file" accept="image/png,image/gif" required>
                <button class="auth-submit" type="submit" @disabled(! $midtransConfigured)>
                    {{ $midtransConfigured ? 'Unggah & lanjut ke pembayaran' : 'Pembayaran belum tersedia' }}
                    @if ($midtransConfigured)<i class="bi bi-arrow-right"></i>@endif
                </button>
            </form>
        @else
            <p>Belum ada paket iklan yang tersedia. Silakan hubungi admin Navora.</p>
        @endif
    </section>

    <section class="onboarding-review-section">
        <h3>Notifikasi dan status iklan</h3>
        @forelse ($campaigns as $campaign)
            @php
                $statusLabels = [
                    'pending_payment' => 'Menunggu pembayaran',
                    'payment_failed' => 'Pembayaran belum berhasil',
                    'paid' => 'Pembayaran diterima · menunggu aset',
                    'awaiting_asset' => 'Disetujui · menunggu aset',
                    'asset_submitted' => 'Pembayaran diterima · menunggu tinjauan admin',
                    'active' => 'Tayang',
                    'rejected' => 'Iklan ditolak admin',
                ];
                $refundLabels = [
                    'not_requested' => 'Tidak ada refund',
                    'pending' => 'Refund sedang diajukan ke Midtrans',
                    'requested' => 'Permintaan refund diterima Midtrans',
                    'refunded' => 'Refund telah selesai',
                    'partially_refunded' => 'Refund sebagian',
                    'cancel_requested' => 'Pembatalan transaksi diterima Midtrans',
                    'cancelled' => 'Pembayaran berhasil dibatalkan',
                    'failed' => 'Refund belum berhasil diajukan; admin akan mencoba kembali',
                ];
            @endphp
            <article class="ad-campaign-card">
                <div>
                    <strong>{{ $campaign->ad_title ?: $campaign->package_name }}</strong>
                    <p>{{ $campaign->placement === 'sidebar' ? 'Slot samping' : 'Banner atas' }} · {{ $campaign->width }} × {{ $campaign->height }} px · {{ $campaign->duration_days }} hari · Rp{{ number_format($campaign->amount, 0, ',', '.') }}</p>
                    @if ($campaign->destination_url)
                        <p>Tautan tujuan: {{ $campaign->destination_url }}</p>
                    @endif
                    <span class="ad-campaign-status" data-ad-status>{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span>
                    @if ($campaign->status === 'pending_payment' && $campaign->snap_token)
                        <form class="ad-campaign-refresh-form" action="{{ route('vendor.advertisements.refresh', $campaign) }}" method="post" data-ad-refresh-form>
                            @csrf
                            <button class="auth-submit" type="submit">Cek status pembayaran</button>
                        </form>
                    @endif
                    @if (in_array($campaign->status, ['active', 'rejected'], true))
                        <div class="ad-campaign-review-notice" role="status">
                            <strong>{{ $campaign->status === 'active' ? 'Notifikasi: iklan diterima' : 'Notifikasi: iklan ditolak' }}</strong>
                            @if ($campaign->review_reason)
                                <span>Alasan/catatan admin: {{ $campaign->review_reason }}</span>
                            @endif
                            @if ($campaign->status === 'rejected')
                                <span>Status refund: {{ $refundLabels[$campaign->refund_status] ?? $campaign->refund_status }}</span>
                            @endif
                        </div>
                    @endif
                    @if ($campaign->paid_at)
                        <div class="ad-campaign-payment-proof">
                            <strong>Bukti pembayaran Midtrans</strong>
                            <span>Order ID: {{ $campaign->order_id }}</span>
                            <span>ID transaksi: {{ $campaign->midtrans_transaction_id ?: 'Tidak dikirim Midtrans' }}</span>
                            <span>Metode: {{ $campaign->payment_type ?: '—' }}</span>
                            <span>Nominal: Rp{{ number_format($campaign->amount, 0, ',', '.') }} · {{ $campaign->paid_at->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                    @if ($campaign->status === 'active')
                        <p>Tayang hingga {{ $campaign->ends_at?->format('d M Y H:i') }}.</p>
                    @endif
                </div>
                @if ($campaign->status === 'awaiting_asset')
                    <form action="{{ route('vendor.advertisements.asset', $campaign) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <label class="auth-field" for="ad-asset-{{ $campaign->id }}">
                            Aset PNG atau GIF (maks. 5 MB)
                            <input id="ad-asset-{{ $campaign->id }}" name="asset" type="file" accept="image/png,image/gif" required>
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

    <div class="ad-payment-feedback" role="status" aria-live="polite" data-ad-payment-feedback hidden></div>
    @if ($midtransConfigured)
        <script src="{{ $midtransSnapUrl }}" data-client-key="{{ $midtransClientKey }}"></script>
        <script>
            (() => {
                const feedback = document.querySelector('[data-ad-payment-feedback]');
                const csrfToken = document.querySelector('input[name="_token"]')?.value
                    ?? document.querySelector('meta[name="csrf-token"]')?.content;

                const showFeedback = (message, isError = false) => {
                    feedback.hidden = false;
                    feedback.textContent = message;
                    feedback.classList.toggle('is-error', isError);
                };

                const refreshStatus = async (url, retries = 0) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Status pembayaran belum dapat diperiksa.');
                    }

                    if (result.payment_status === 'paid' || result.status === 'asset_submitted') {
                        showFeedback('Pembayaran berhasil. Iklan menunggu tinjauan admin.');
                        window.setTimeout(() => window.location.reload(), 1000);
                        return result;
                    }

                    if (result.payment_status === 'pending' && retries > 0) {
                        await new Promise(resolve => window.setTimeout(resolve, 1500));
                        return refreshStatus(url, retries - 1);
                    }

                    showFeedback(
                        result.payment_status === 'pending'
                            ? 'Pembayaran masih menunggu konfirmasi Midtrans. Status akan diperbarui setelah terkonfirmasi.'
                            : `Status pembayaran: ${result.payment_status}.`,
                    );

                    if (result.payment_status !== 'pending') {
                        window.setTimeout(() => window.location.reload(), 1000);
                    }

                    return result;
                };

                const finishCheckout = async (statusUrl, retries) => {
                    try {
                        const result = await refreshStatus(statusUrl, retries);

                        if (result.payment_status === 'pending') {
                            window.setTimeout(() => window.location.reload(), 1200);
                        }
                    } catch (error) {
                        showFeedback(error.message, true);
                        window.setTimeout(() => window.location.reload(), 2500);
                    }
                };

                const startSnap = (token, statusUrl) => {
                    if (! window.snap) {
                        showFeedback('Popup pembayaran gagal dimuat. Muat ulang halaman dan coba kembali.', true);
                        return;
                    }

                    window.snap.pay(token, {
                        onSuccess: () => finishCheckout(statusUrl, 3),
                        onPending: () => finishCheckout(statusUrl, 2),
                        onError: () => finishCheckout(statusUrl, 2),
                        onClose: () => finishCheckout(statusUrl, 2),
                    });
                };

                document.querySelectorAll('[data-ad-refresh-form]').forEach(form => {
                    form.addEventListener('submit', event => {
                        event.preventDefault();
                        const button = form.querySelector('button');
                        button.disabled = true;
                        showFeedback('Memeriksa status transaksi langsung ke Midtrans...');
                        refreshStatus(form.action, 2)
                            .catch(error => showFeedback(error.message, true))
                            .finally(() => { button.disabled = false; });
                    });
                });

                document.querySelector('[data-ad-checkout-form]')?.addEventListener('submit', async event => {
                    event.preventDefault();
                    const form = event.currentTarget;
                    const button = form.querySelector('button[type="submit"]');

                    if (! window.snap) {
                        showFeedback('Popup Midtrans belum termuat. Muat ulang halaman lalu coba kembali.', true);
                        return;
                    }

                    button.disabled = true;
                    window.snap.show();

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: new FormData(form),
                        });
                        const result = await response.json();

                        if (!response.ok) {
                            const validationMessage = result.errors
                                ? Object.values(result.errors).flat().join(' ')
                                : result.message;
                            throw new Error(validationMessage || 'Checkout belum berhasil dibuat.');
                        }

                        startSnap(result.token, result.status_url);
                    } catch (error) {
                        window.snap.hide();
                        showFeedback(error.message || 'Checkout belum berhasil dibuat.', true);
                        button.disabled = false;
                    }
                });

                @if (session('snapToken'))
                    startSnap(@json(session('snapToken')), @json(session('statusUrl')));
                @endif
            })();
        </script>
    @endif
@endsection
