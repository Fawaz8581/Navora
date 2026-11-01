@extends('admin.layout')

@section('title', 'Persetujuan katalog')
@section('meta-description', 'Tinjau dan setujui katalog produk vendor Navora.')

@section('admin-content')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> ADMIN NAVORA</span>
        <h2>Persetujuan katalog vendor</h2>
        <p>Setiap katalog baru perlu diperiksa dan disetujui sebelum tampil di pencarian UMKM.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-check-circle"></i>{{ session('status') }}</div>
    @endif

    <nav class="onboarding-progress" aria-label="Filter status katalog">
        @foreach ([\App\Models\VendorListing::STATUS_PENDING => 'Menunggu ACC', \App\Models\VendorListing::STATUS_ACTIVE => 'Aktif', 'all' => 'Semua'] as $value => $label)
            <a class="onboarding-progress-step {{ $status === $value ? 'is-current' : '' }}" href="{{ route('admin.vendor-listings.index', ['status' => $value]) }}" @if ($status === $value) aria-current="page" @endif>
                <small>{{ $label }}</small>
            </a>
        @endforeach
    </nav>

    @if ($listings->isEmpty())
        <div class="onboarding-review-section">
            <h3>Tidak ada katalog pada daftar ini</h3>
            <p>Pengajuan katalog vendor akan muncul setelah dikirim untuk ditinjau admin.</p>
        </div>
    @else
        <div class="onboarding-review">
            @foreach ($listings as $listing)
                <section class="onboarding-review-section">
                    <div class="onboarding-review-heading">
                        <h3>{{ $listing->name }}</h3>
                        <span>{{ match ($listing->status) {
                            \App\Models\VendorListing::STATUS_PENDING => 'Menunggu ACC',
                            \App\Models\VendorListing::STATUS_ACTIVE => 'Aktif',
                            default => 'Draft',
                        } }}</span>
                    </div>
                    <div class="onboarding-review-row"><span>Vendor</span><strong>{{ $listing->vendor?->businessProfile?->business_name ?: $listing->vendor?->name ?: 'Vendor' }}</strong></div>
                    <div class="onboarding-review-row"><span>Pemilik akun</span><strong>{{ $listing->vendor?->name }} ({{ $listing->vendor?->email }})</strong></div>
                    <div class="onboarding-review-row"><span>Jenis / material</span><strong>{{ ucfirst($listing->category) }} · {{ $listing->material }}</strong></div>
                    <div class="onboarding-review-row"><span>MOQ</span><strong>{{ number_format($listing->minimum_order_quantity, 0, ',', '.') }} pcs</strong></div>
                    <p>{{ $listing->description }}</p>
                    <small>Diajukan {{ $listing->created_at->format('d M Y, H:i') }}</small>
                    @if ($listing->status === \App\Models\VendorListing::STATUS_PENDING)
                        <form action="{{ route('admin.vendor-listings.approve', $listing) }}" method="post">
                            @csrf
                            <button class="auth-submit" type="submit">Setujui dan aktifkan <i class="bi bi-check2"></i></button>
                        </form>
                    @elseif ($listing->approver)
                        <small>Disetujui {{ $listing->approved_at?->format('d M Y, H:i') }} oleh {{ $listing->approver->name }}</small>
                    @endif
                </section>
            @endforeach
        </div>
        {{ $listings->links() }}
    @endif
@endsection
