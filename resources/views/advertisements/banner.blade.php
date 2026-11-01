@if ($activeAdvertisement)
    <aside class="navora-ad-banner" aria-label="Iklan berbayar">
        <span class="navora-ad-label">IKLAN</span>
        <img
            src="{{ route('advertisements.asset', $activeAdvertisement) }}"
            alt="Iklan {{ $activeAdvertisement->package_name }}"
            width="{{ $activeAdvertisement->width }}"
            height="{{ $activeAdvertisement->height }}"
        >
    </aside>
@endif
