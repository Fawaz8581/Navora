@if ($sidebarAdvertisement)
    <aside class="navora-ad-sidebar" aria-label="Iklan berbayar">
        <span class="navora-ad-label">IKLAN</span>
        <img
            src="{{ route('advertisements.asset', $sidebarAdvertisement) }}"
            alt="Iklan {{ $sidebarAdvertisement->package_name }}"
            width="{{ $sidebarAdvertisement->width }}"
            height="{{ $sidebarAdvertisement->height }}"
        >
    </aside>
@endif
