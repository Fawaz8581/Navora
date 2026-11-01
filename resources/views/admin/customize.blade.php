@extends('admin.layout')

@section('title', 'Customize iklan')
@section('meta-description', 'Atur paket iklan dan tinjau pembayaran serta aset iklan vendor.')

@section('admin-content')
    <section class="admin-welcome">
        <div>
            <span class="admin-eyebrow">CUSTOMIZE</span>
            <h1>Pengaturan iklan Navora</h1>
            <p>Atur ukuran, harga, dan durasi paket; setujui pembayaran; lalu tinjau dan terbitkan aset vendor.</p>
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
            <div><span class="admin-eyebrow">PAKET DAN TARIF</span><h2>Slot iklan</h2></div>
        </div>
        <p class="admin-customize-copy">Pilih posisi iklan atas atau samping. Dimensi adalah ukuran rekomendasi aset dalam piksel; tampilan akan menyesuaikan ukuran layar.</p>
        <form class="admin-ad-package-form" action="{{ route('admin.customize.packages.store') }}" method="post">
            @csrf
            <label>Nama paket<input name="name" value="{{ old('name') }}" maxlength="100" required></label>
            <label>Posisi iklan<select name="placement" required><option value="top" @selected(old('placement', 'top') === 'top')>Banner atas</option><option value="sidebar" @selected(old('placement') === 'sidebar')>Samping</option></select></label>
            <label>Lebar (px)<input name="width" type="number" min="50" max="3000" value="{{ old('width', 1200) }}" required></label>
            <label>Tinggi (px)<input name="height" type="number" min="50" max="3000" value="{{ old('height', 300) }}" required></label>
            <label>Harga (Rp)<input name="price" type="number" min="1000" max="1000000000" value="{{ old('price') }}" required></label>
            <label>Durasi (hari)<input name="duration_days" type="number" min="1" max="365" value="{{ old('duration_days', 7) }}" required></label>
            <label class="admin-ad-active"><input name="is_active" type="checkbox" value="1" checked> Aktif</label>
            <button class="admin-table-action" type="submit">Tambah paket</button>
        </form>

        @forelse ($packages as $package)
            <form class="admin-ad-package-form admin-ad-package-edit" action="{{ route('admin.customize.packages.update', $package) }}" method="post">
                @csrf
                @method('PATCH')
                <label>Nama paket<input name="name" value="{{ $package->name }}" maxlength="100" required></label>
                <label>Posisi iklan<select name="placement" required><option value="top" @selected($package->placement === 'top')>Banner atas</option><option value="sidebar" @selected($package->placement === 'sidebar')>Samping</option></select></label>
                <label>Lebar (px)<input name="width" type="number" min="50" max="3000" value="{{ $package->width }}" required></label>
                <label>Tinggi (px)<input name="height" type="number" min="50" max="3000" value="{{ $package->height }}" required></label>
                <label>Harga (Rp)<input name="price" type="number" min="1000" max="1000000000" value="{{ $package->price }}" required></label>
                <label>Durasi (hari)<input name="duration_days" type="number" min="1" max="365" value="{{ $package->duration_days }}" required></label>
                <label class="admin-ad-active"><input name="is_active" type="checkbox" value="1" @checked($package->is_active)> Aktif</label>
                <button class="admin-table-action" type="submit">Simpan</button>
            </form>
        @empty
            <div class="admin-empty-state"><i class="bi bi-megaphone"></i><strong>Belum ada paket</strong><span>Tambahkan paket iklan agar vendor dapat memesan.</span></div>
        @endforelse
    </section>

    <section class="admin-panel admin-customize-section">
        <div class="admin-panel-heading">
            <div><span class="admin-eyebrow">PEMBAYARAN DAN ASET</span><h2>Campaign vendor</h2></div>
        </div>
        @forelse ($campaigns as $campaign)
            @php
                $statusLabels = [
                    'pending_payment' => 'Menunggu pembayaran',
                    'payment_failed' => 'Pembayaran belum berhasil',
                    'paid' => 'Lunas · menunggu persetujuan',
                    'awaiting_asset' => 'Disetujui · menunggu aset vendor',
                    'asset_submitted' => 'Aset menunggu tinjauan',
                    'active' => 'Tayang',
                ];
            @endphp
            <article class="admin-ad-campaign">
                <div class="admin-ad-campaign-heading">
                    <div><strong>{{ $campaign->package_name }}</strong><span>{{ $campaign->vendor->name }} · {{ $campaign->vendor->email }}</span></div>
                    <span class="admin-status-pill">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span>
                </div>
                <p>{{ $campaign->placement === 'sidebar' ? 'Slot samping' : 'Banner atas' }} · {{ $campaign->width }} × {{ $campaign->height }} px · {{ $campaign->duration_days }} hari · Rp{{ number_format($campaign->amount, 0, ',', '.') }} · Status Midtrans: {{ $campaign->payment_status }}</p>
                <small>Order ID: {{ $campaign->order_id }} · Dibuat {{ $campaign->created_at->format('d M Y H:i') }}</small>
                @if ($campaign->status === 'paid')
                    <div class="admin-ad-actions">
                        <form action="{{ route('admin.customize.campaigns.review', $campaign) }}" method="post">
                            @csrf
                            <button class="admin-table-action" type="submit">Konfirmasi pembayaran &amp; setujui</button>
                        </form>
                    </div>
                @elseif ($campaign->status === 'asset_submitted')
                    <div class="admin-ad-preview">
                        <img src="{{ route('admin.customize.campaigns.asset', $campaign) }}" alt="Pratinjau aset iklan {{ $campaign->package_name }}">
                    </div>
                    <form action="{{ route('admin.customize.campaigns.publish', $campaign) }}" method="post">
                        @csrf
                        <button class="admin-table-action" type="submit">Setujui aset &amp; tayangkan</button>
                    </form>
                @elseif ($campaign->status === 'active')
                    <p>Tayang hingga {{ $campaign->ends_at?->format('d M Y H:i') }}.</p>
                @endif
            </article>
        @empty
            <div class="admin-empty-state"><i class="bi bi-hourglass-split"></i><strong>Belum ada campaign iklan</strong><span>Pemesanan vendor akan muncul di sini.</span></div>
        @endforelse
        <div class="admin-pagination">{{ $campaigns->links() }}</div>
    </section>
@endsection
