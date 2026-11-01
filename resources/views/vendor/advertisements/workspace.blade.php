<section class="navora-page-panel" data-page-panel="advertisements" role="tabpanel" hidden>
    <div class="navora-page-title-row">
        <div>
            <div class="navora-breadcrumb"><span>Ruang vendor</span><i class="bi bi-chevron-right"></i><span>Iklan</span></div>
            <h1>Kelola iklan</h1>
            <p>Buat, pantau, dan ubah iklan usahamu tanpa meninggalkan ruang kerja.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="navora-note" role="status"><i class="bi bi-info-circle"></i><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="navora-order-error" role="alert">
            <strong>Permintaan belum dapat diproses.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @unless ($midtransConfigured)
        <div class="navora-note" role="status"><i class="bi bi-info-circle"></i><span>Pembayaran iklan sementara belum tersedia. Silakan hubungi admin Navora.</span></div>
    @endunless

    <section class="navora-panel ad-workspace-section">
        <div class="navora-panel-heading"><h2>Pasang iklan</h2><span class="navora-demo-label"><i class="bi bi-megaphone"></i> IKLAN VENDOR</span></div>
        <p>Tarif banner atas Rp200.000 (1200 × 300 px) dan banner samping Rp100.000 (300 × 900 px). Gambar akan disesuaikan ke ukuran tayang tetap. Format: PNG, JPG/JPEG, GIF, WebP, BMP, atau AVIF; maksimal 5 MB.</p>
        @if ($advertisementPackages->isNotEmpty())
            <form class="ad-campaign-form" action="{{ route('vendor.advertisements.store') }}" method="post" enctype="multipart/form-data" data-ad-checkout-form>
                @csrf
                <label class="auth-field" for="ad-package">Posisi dan durasi</label>
                <select id="ad-package" name="ad_package_id" required>
                    @foreach ($advertisementPackages as $package)
                        <option value="{{ $package->id }}" @selected(old('ad_package_id', $advertisementPackages->first()->id) == $package->id)>
                            {{ $package->placement === 'sidebar' ? 'Banner samping · Rp100.000 · 300 × 900 px' : 'Banner atas · Rp200.000 · 1200 × 300 px' }} · {{ $package->duration_days }} hari
                        </option>
                    @endforeach
                </select>
                <label class="auth-field" for="ad-title">Judul iklan</label>
                <input id="ad-title" name="ad_title" type="text" maxlength="120" value="{{ old('ad_title') }}" placeholder="Contoh: Kemasan premium untuk produkmu" required>
                <label class="auth-field" for="ad-destination-url">Tautan tujuan iklan (HTTPS)</label>
                <input id="ad-destination-url" name="destination_url" type="url" value="{{ old('destination_url') }}" placeholder="https://contoh.com/promo" required>
                <label class="auth-field" for="ad-asset">Gambar iklan (PNG, JPG, GIF, WebP, BMP, atau AVIF; maks. 5 MB)</label>
                <input id="ad-asset" name="asset" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/bmp,image/avif" required>
                <button class="navora-app-primary" type="submit" @disabled(! $midtransConfigured)>
                    {{ $midtransConfigured ? 'Lanjut ke pembayaran Midtrans' : 'Pembayaran belum tersedia' }}
                    @if ($midtransConfigured)<i class="bi bi-arrow-right"></i>@endif
                </button>
            </form>
        @else
            <p>Belum ada paket iklan yang tersedia. Silakan hubungi admin Navora.</p>
        @endif
    </section>

    <section class="navora-panel ad-workspace-section">
        <div class="navora-panel-heading"><h2>Iklan saya</h2></div>
        @forelse ($vendorAdCampaigns as $campaign)
            @php
                $statusLabels = [
                    'pending_payment' => 'Menunggu pembayaran',
                    'payment_failed' => 'Pembayaran belum berhasil',
                    'paid' => 'Pembayaran diterima · menunggu aset',
                    'awaiting_asset' => 'Disetujui · menunggu aset',
                    'asset_submitted' => 'Menunggu tinjauan admin',
                    'active' => 'Tayang',
                    'rejected' => 'Ditolak admin',
                ];
                $refundLabels = [
                    'pending' => 'Refund sedang diajukan ke Midtrans',
                    'requested' => 'Permintaan refund diterima Midtrans',
                    'refunded' => 'Refund telah selesai',
                    'partially_refunded' => 'Refund sebagian',
                    'cancel_requested' => 'Pembatalan transaksi diterima Midtrans',
                    'cancelled' => 'Pembayaran berhasil dibatalkan',
                    'failed' => 'Refund belum berhasil diajukan; admin akan mencoba kembali',
                ];
            @endphp
            <article class="ad-campaign-card" id="ad-campaign-{{ $campaign->id }}">
                <div>
                    <strong>{{ $campaign->ad_title ?: $campaign->package_name }}</strong>
                    <p>{{ $campaign->placement === 'sidebar' ? 'Banner samping · 300 × 900 px' : 'Banner atas · 1200 × 300 px' }} · {{ $campaign->duration_days }} hari · Rp{{ number_format($campaign->amount, 0, ',', '.') }}</p>
                    @if ($campaign->destination_url)
                        <p>Tautan tujuan: <a href="{{ $campaign->destination_url }}" target="_blank" rel="noopener noreferrer">{{ $campaign->destination_url }}</a></p>
                    @endif
                    <span class="ad-campaign-status" data-ad-status>{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span>
                    @if ($campaign->status === 'pending_payment' && $campaign->snap_token)
                        <form class="ad-campaign-refresh-form" action="{{ route('vendor.advertisements.refresh', $campaign) }}" method="post" data-ad-refresh-form>
                            @csrf
                            <button class="btn navora-app-outline" type="submit">Cek status pembayaran</button>
                        </form>
                    @endif
                    @if (in_array($campaign->status, ['active', 'rejected'], true))
                        <div class="ad-campaign-review-notice" role="status">
                            <strong>{{ $campaign->status === 'active' ? 'Iklan diterima dan ditayangkan' : 'Iklan ditolak admin' }}</strong>
                            @if ($campaign->review_reason)<span>Catatan admin: {{ $campaign->review_reason }}</span>@endif
                            @if ($campaign->status === 'rejected')
                                <span>Status refund: {{ $refundLabels[$campaign->refund_status] ?? 'Sedang diproses' }}</span>
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
                @if (in_array($campaign->status, ['asset_submitted', 'active'], true) && $campaign->payment_status === 'paid')
                    <form class="ad-campaign-edit-form" action="{{ route('vendor.advertisements.update', $campaign) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <strong>Edit iklan</strong>
                        <label class="auth-field" for="edit-ad-title-{{ $campaign->id }}">Judul iklan</label>
                        <input id="edit-ad-title-{{ $campaign->id }}" name="ad_title" type="text" maxlength="120" value="{{ $campaign->ad_title }}" required>
                        <label class="auth-field" for="edit-ad-url-{{ $campaign->id }}">Tautan tujuan (HTTPS)</label>
                        <input id="edit-ad-url-{{ $campaign->id }}" name="destination_url" type="url" value="{{ $campaign->destination_url }}" required>
                        <label class="auth-field" for="edit-ad-asset-{{ $campaign->id }}">Ganti gambar (PNG, JPG, GIF, WebP, BMP, atau AVIF; maks. 5 MB)</label>
                        <input id="edit-ad-asset-{{ $campaign->id }}" name="asset" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/bmp,image/avif">
                        @if ($campaign->status === 'active')
                            <small>Perubahan akan menghentikan tayang sementara dan mengirim ulang iklan ke tinjauan admin.</small>
                        @endif
                        <button class="btn navora-app-primary" type="submit">Simpan perubahan <i class="bi bi-check2"></i></button>
                    </form>
                @elseif ($campaign->status === 'awaiting_asset')
                    <form class="ad-campaign-edit-form" action="{{ route('vendor.advertisements.asset', $campaign) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <label class="auth-field" for="ad-asset-{{ $campaign->id }}">Gambar iklan (PNG, JPG, GIF, WebP, BMP, atau AVIF; maks. 5 MB)</label>
                        <input id="ad-asset-{{ $campaign->id }}" name="asset" type="file" accept="image/png,image/jpeg,image/gif,image/webp,image/bmp,image/avif" required>
                        <button class="btn navora-app-primary" type="submit">Kirim gambar untuk ditinjau <i class="bi bi-send"></i></button>
                    </form>
                @endif
            </article>
        @empty
            <p>Belum ada iklan. Gunakan formulir di atas untuk memulai.</p>
        @endforelse
    </section>

    <div class="ad-payment-feedback" role="status" aria-live="polite" data-ad-payment-feedback hidden></div>
</section>

@if ($midtransConfigured)
    <script src="{{ $midtransSnapUrl }}" data-client-key="{{ $midtransClientKey }}"></script>
    <script>
        (() => {
            const feedback = document.querySelector('[data-ad-payment-feedback]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const showFeedback = (message, isError = false) => {
                feedback.hidden = false;
                feedback.textContent = message;
                feedback.classList.toggle('is-error', isError);
            };
            const refreshStatus = async (url, retries = 0) => {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
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
                showFeedback(result.payment_status === 'pending'
                    ? 'Pembayaran masih menunggu konfirmasi Midtrans.'
                    : `Status pembayaran: ${result.payment_status}.`);
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
                }
            };
            const startSnap = (token, statusUrl) => {
                if (!window.snap) {
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

            const refreshForms = [...document.querySelectorAll('[data-ad-refresh-form]')];
            refreshForms.forEach(form => form.addEventListener('submit', event => {
                event.preventDefault();
                const button = form.querySelector('button');
                button.disabled = true;
                showFeedback('Memeriksa status transaksi langsung ke Midtrans...');
                refreshStatus(form.action, 2)
                    .catch(error => showFeedback(error.message, true))
                    .finally(() => { button.disabled = false; });
            }));

            if (refreshForms[0]) {
                let attempts = 0;
                const checkPayment = async () => {
                    if (attempts >= 10) return;
                    attempts++;
                    try {
                        const result = await refreshStatus(refreshForms[0].action);
                        if (result.payment_status === 'pending') window.setTimeout(checkPayment, 30000);
                    } catch (error) {
                        showFeedback(error.message, true);
                        if (attempts < 10) window.setTimeout(checkPayment, 30000);
                    }
                };
                window.setTimeout(checkPayment, 5000);
            }

            document.querySelector('[data-ad-checkout-form]')?.addEventListener('submit', async event => {
                event.preventDefault();
                const form = event.currentTarget;
                const button = form.querySelector('button[type="submit"]');
                if (!window.snap) {
                    showFeedback('Popup Midtrans belum termuat. Muat ulang halaman lalu coba kembali.', true);
                    return;
                }
                button.disabled = true;
                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: new FormData(form),
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        const message = result.errors ? Object.values(result.errors).flat().join(' ') : result.message;
                        throw new Error(message || 'Checkout belum berhasil dibuat.');
                    }
                    startSnap(result.token, result.status_url);
                } catch (error) {
                    showFeedback(error.message || 'Checkout belum berhasil dibuat.', true);
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
@endif
