@if ($sidebarAdvertisement)
    <aside class="navora-ad-sidebar" aria-label="Iklan berbayar">
        <span class="navora-ad-label">IKLAN</span>
        <a href="{{ $sidebarAdvertisement->destination_url ?: route('vendor.advertisements.index') }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $sidebarAdvertisement->ad_title ?: 'Lihat iklan' }}">
            <img
                src="{{ route('advertisements.asset', $sidebarAdvertisement) }}"
                alt="{{ $sidebarAdvertisement->ad_title ?: 'Iklan '.$sidebarAdvertisement->package_name }}"
                width="{{ $sidebarAdvertisement->width }}"
                height="{{ $sidebarAdvertisement->height }}"
            >
        </a>
    </aside>
@else
    <aside class="navora-ad-sidebar navora-ad-sidebar-promo" aria-label="Pasang iklan di Navora">
        <span class="navora-ad-label">RUANG IKLAN</span>
        <a class="navora-ad-promo-link" href="{{ route('vendor.advertisements.index') }}">
            <strong>Ingin pasang iklan?</strong>
            <span>Jangkau lebih banyak UMKM di Navora.</span>
            <span class="navora-ad-promo-action">Klik di sini <i class="bi bi-arrow-up-right"></i></span>
        </a>
    </aside>
@endif
