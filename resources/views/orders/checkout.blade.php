<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout Pesanan — Navora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/navora-app.css').'?v='.filemtime(public_path('css/navora-app.css')) }}" rel="stylesheet">
</head>
<body class="navora-app-body navora-checkout-body"
    data-workspace-url="{{ route('workspace') }}"
    data-midtrans-ready="{{ $midtransConfigured ? 'true' : 'false' }}"
    data-rajaongkir-locations-url="{{ route('rajaongkir.locations') }}"
    data-delivery-details-url="{{ route('orders.delivery-details', $order) }}"
    data-shipping-rates-url="{{ route('orders.shipping-rates', $order) }}"
    data-shipping-select-url="{{ route('orders.shipping-select', $order) }}"
    data-payment-url="{{ route('orders.checkout', $order) }}"
    data-payment-refresh-url="{{ route('orders.payment-refresh', $order) }}"
    data-profile-contact-name="{{ $buyerProfile?->business_name ?: $buyer->name }}"
    data-profile-contact-phone="{{ $buyerProfile?->phone ?: '' }}"
    data-profile-address="{{ $buyerProfile?->business_address ?: '' }}"
    data-profile-area-id="{{ $buyerProfile?->rajaongkir_area_id ?: '' }}"
    data-profile-area-name="{{ $buyerProfile?->rajaongkir_area_name ?: '' }}">
    <a class="navora-skip-link" href="#checkout-content">Lewati ke konten utama</a>
    <header class="navora-checkout-header">
        <a class="navora-app-brand" href="{{ route('workspace') }}">
            <span class="navora-app-logo"><i class="bi bi-box-seam"></i></span>
            <span>Navora<span class="navora-brand-dot">.</span><small>CHECKOUT PESANAN</small></span>
        </a>
        <a class="btn navora-app-outline" href="{{ route('workspace') }}"><i class="bi bi-arrow-left"></i> Kembali ke pesanan</a>
    </header>

    <main class="navora-checkout-main" id="checkout-content">
        <div class="navora-checkout-heading">
            <span class="navora-panel-kicker">LANGKAH TERAKHIR</span>
            <h1>Checkout pesanan</h1>
            <p>Lengkapi penerima dan alamat tujuan, pilih jasa ekspedisi, lalu lanjutkan pembayaran.</p>
        </div>

        <div class="navora-checkout-layout">
            <section class="navora-checkout-card">
                <div class="navora-checkout-order">
                    <span class="navora-checkout-icon"><i class="bi bi-box-seam"></i></span>
                    <div>
                        <span class="navora-panel-kicker">{{ $order->type === \App\Models\Order::TYPE_GROUP ? 'PESANAN PATUNGAN' : 'PESANAN LANGSUNG' }}</span>
                        <h2>{{ $order->product_name }}</h2>
                        <p>{{ number_format($quantity, 0, ',', '.') }} pcs · {{ $order->vendor?->businessProfile?->business_name ?: $order->vendor?->name }}</p>
                    </div>
                </div>

                <div class="navora-checkout-addresses">
                    <article>
                        <span class="navora-checkout-label"><i class="bi bi-shop"></i> Alamat pengirim</span>
                        <strong>{{ $order->vendor?->businessProfile?->business_name ?: $order->vendor?->name }}</strong>
                        <p>{{ $vendorProfile?->business_address ?: 'Vendor belum mengisi alamat lengkap.' }}</p>
                        <small>{{ $vendorProfile?->rajaongkir_area_name ?: 'Area asal belum dipilih vendor' }}</small>
                    </article>
                    <span class="navora-checkout-route" aria-hidden="true"><i class="bi bi-arrow-down"></i></span>
                    <article>
                        <span class="navora-checkout-label"><i class="bi bi-geo-alt"></i> Alamat penerima</span>
                        <strong>{{ $buyer->name }}</strong>
                        <p data-current-delivery-address>{{ $delivery['address'] ?: 'Isi alamat penerima di bawah.' }}</p>
                        <small data-current-delivery-area>{{ $delivery['area_name'] ?: 'Pilih area tujuan di bawah' }}</small>
                    </article>
                </div>

                <form class="navora-checkout-form" data-checkout-form>
                    <div class="navora-checkout-section-heading">
                        <span>01</span>
                        <div><h2>Biodata penerima</h2><p>Pilih gunakan alamat profil usaha atau masukkan manual.</p></div>
                    </div>
                    @if ($buyerProfile?->business_address || $buyerProfile?->rajaongkir_area_id)
                        <div class="navora-checkout-address-mode" style="margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; font-weight: 600;">
                                <input type="radio" name="address_source" value="profile" checked>
                                <span><i class="bi bi-person-badge"></i> Gunakan alamat dari profil</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; font-weight: 600;">
                                <input type="radio" name="address_source" value="manual">
                                <span><i class="bi bi-pencil-square"></i> Masukkan alamat manual</span>
                            </label>
                        </div>
                    @endif
                    <div class="navora-checkout-fields">
                        <label class="navora-checkout-field">
                            <span>Nama penerima</span>
                            <input name="delivery_contact_name" type="text" maxlength="255" autocomplete="name" value="{{ $delivery['contact_name'] ?: $buyer->name }}" required>
                        </label>
                        <label class="navora-checkout-field">
                            <span>Nomor telepon</span>
                            <input name="delivery_contact_phone" type="tel" maxlength="40" autocomplete="tel" placeholder="+62..." value="{{ $delivery['contact_phone'] }}" required>
                        </label>
                        <label class="navora-checkout-field navora-checkout-field-wide">
                            <span>Alamat lengkap penerima</span>
                            <textarea name="delivery_address" rows="3" maxlength="1000" autocomplete="street-address" placeholder="Nama jalan, nomor rumah, RT/RW, patokan" required>{{ $delivery['address'] }}</textarea>
                        </label>
                        <div class="navora-checkout-field navora-checkout-field-wide" data-location-picker>
                            <label for="checkout-delivery-area">Kecamatan / kota tujuan</label>
                            <input id="checkout-delivery-area" name="delivery_area_name" type="search" data-rajaongkir-location autocomplete="off" placeholder="Cari kecamatan atau kota" value="{{ $delivery['area_name'] }}" required>
                            <input name="delivery_area_id" type="hidden" data-rajaongkir-area-id value="{{ $delivery['area_id'] }}" required>
                            <small data-location-validation hidden>Pilih area dari hasil pencarian lokasi.</small>
                        </div>
                    </div>

                    <div class="navora-checkout-section-heading navora-checkout-shipping-heading">
                        <span>02</span>
                        <div><h2>Pilih jasa ekspedisi</h2><p>Alamat pengirim mengikuti profil vendor; area tujuan mengikuti pilihan UMKM.</p></div>
                    </div>
                    <label class="navora-checkout-field navora-checkout-courier">
                        <span>Jasa ekspedisi / pengiriman</span>
                        <select name="courier" data-checkout-courier required>
                            <option value="">Pilih pengiriman</option>
                            <option value="vendor" @selected(($shippingCourier ?? '') === 'vendor')>ONGKIR VIA VENDOR (GRATIS / RP 0)</option>
                            @foreach ($couriers as $courier)
                                <option value="{{ $courier }}" @selected(($shippingCourier ?? '') === $courier)>EKSPEDISI {{ strtoupper($courier) }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($simulatedRates)
                        <p class="navora-checkout-rate-note"><i class="bi bi-info-circle"></i> Ongkir adalah tarif simulasi dan tidak berubah berdasarkan ukuran paket.</p>
                    @else
                        <p class="navora-checkout-rate-note"><i class="bi bi-info-circle"></i> Tarif dihitung berdasarkan berat kiriman dan layanan ekspedisi yang tersedia.</p>
                    @endif
                    <button class="btn navora-app-outline navora-checkout-calculate" type="submit" data-checkout-calculate>
                        <i class="bi bi-truck"></i> Simpan alamat & hitung ongkir
                    </button>
                </form>

                <div class="navora-checkout-feedback" data-checkout-feedback role="status" aria-live="polite" hidden></div>
                <div class="navora-checkout-rates" data-checkout-rates hidden>
                    <h3>Pilih layanan dan tarif</h3>
                    <div data-checkout-rate-list></div>
                </div>
            </section>

            <aside class="navora-checkout-summary">
                <div class="navora-checkout-summary-card">
                    <span class="navora-panel-kicker">RINGKASAN PEMBAYARAN</span>
                    <h2>{{ $order->product_name }}</h2>
                    <div class="navora-checkout-cost-row"><span>Subtotal produk</span><strong data-checkout-subtotal data-value="{{ $subtotalAmount }}">Rp{{ number_format($subtotalAmount, 0, ',', '.') }}</strong></div>
                    <div class="navora-checkout-cost-row"><span>Biaya platform</span><strong data-checkout-platform-fee data-value="{{ $platformFeeAmount }}">Rp{{ number_format($platformFeeAmount, 0, ',', '.') }}</strong></div>
                    <div class="navora-checkout-cost-row"><span>Ongkir <small data-checkout-service>{{ $shippingService ? strtoupper($shippingCourier).' · '.$shippingService : 'Belum dipilih' }}</small></span><strong data-checkout-shipping data-value="{{ $shippingAmount }}">Rp{{ number_format($shippingAmount, 0, ',', '.') }}</strong></div>
                    <div class="navora-checkout-total"><span>Total pembayaran</span><strong data-checkout-total data-value="{{ $paymentAmount }}">Rp{{ number_format($paymentAmount, 0, ',', '.') }}</strong></div>
                    <button class="btn navora-app-primary navora-checkout-pay" type="button" data-checkout-pay @disabled(! $hasShippingSelection || ! $midtransConfigured)>
                        <i class="bi bi-shield-lock"></i> Bayar via Midtrans
                    </button>
                    @unless ($midtransConfigured)
                        <p class="navora-checkout-setup-warning" role="alert">{{ $midtransSetupMessage }}</p>
                    @endunless
                    <p class="navora-checkout-secure"><i class="bi bi-lock"></i> Pembayaran diproses dengan aman melalui Midtrans.</p>
                </div>
            </aside>
        </div>
    </main>

    @if ($midtransConfigured)
        <script src="{{ rtrim(config('services.midtrans.base_url'), '/').'/snap/snap.js' }}" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif
    <script src="{{ asset('js/rajaongkir-location.js').'?v='.filemtime(public_path('js/rajaongkir-location.js')) }}" defer></script>
    <script src="{{ asset('js/order-checkout.js').'?v='.filemtime(public_path('js/order-checkout.js')) }}" defer></script>
</body>
</html>
