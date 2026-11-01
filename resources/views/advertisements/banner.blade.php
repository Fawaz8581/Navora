@if ($activeAdvertisement)
    <aside class="navora-ad-banner" aria-label="Iklan berbayar">
        <span class="navora-ad-label">IKLAN</span>
        <a href="{{ $activeAdvertisement->destination_url ?: route('vendor.advertisements.index') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $activeAdvertisement->ad_title ?: 'Lihat iklan' }}">
            <img
                src="{{ route('advertisements.asset', $activeAdvertisement) }}"
                alt="{{ $activeAdvertisement->ad_title ?: 'Iklan '.$activeAdvertisement->package_name }}"
                width="{{ $activeAdvertisement->width }}"
                height="{{ $activeAdvertisement->height }}"
            >
        </a>
    </aside>
@else
    <aside class="navora-ad-banner navora-ad-promo" aria-label="Pasang iklan di Navora">
        <span class="navora-ad-label">RUANG IKLAN</span>
        <a class="navora-ad-promo-link" href="{{ route('vendor.advertisements.index') }}">
            <span class="navora-ad-promo-copy">
                <strong>Ingin pasang iklan?</strong>
                <span>Promosikan usahamu kepada komunitas UMKM Navora.</span>
            </span>
            <span class="navora-ad-promo-action">Klik di sini <i class="bi bi-arrow-up-right"></i></span>
        </a>
    </aside>
@endif
