@extends('auth.partials.layout')

@section('title', $isCompleted ? 'Profil usaha' : 'Lengkapi profil usaha')
@section('meta-description', 'Lengkapi profil usaha Navora agar pencarian vendor dan kebutuhan kemasan lebih sesuai.')
@section('auth-form-class', $isEditing ? 'onboarding-form-wrap' : '')

@section('auth-form')
    @php
        $roleLabel = $role === 'vendor' ? 'Vendor' : 'UMKM';
    @endphp

    <div class="auth-form-heading onboarding-heading">
        <span class="auth-form-eyebrow"><span></span> {{ $isCompleted ? 'PROFIL USAHA' : 'PERSIAPAN AKUN · ' . $roleLabel }}</span>
        <h2>{{ $isEditing ? 'Lengkapi profil usaha' : 'Profil usahamu' }}</h2>
        <p>{{ $isEditing ? 'Lengkapi profil dan kebutuhan bisnis dalam satu halaman.' : 'Data profil tersimpan di akun Navora dan dapat kamu perbarui kapan saja.' }}</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Periksa kembali informasi berikut.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($isEditing)
        <form action="{{ route('onboarding.update', ['step' => 1]) }}" method="post" enctype="multipart/form-data" class="auth-form onboarding-form">
            @csrf
            @include('onboarding.partials.business-fields')
            <section class="onboarding-form-section">
                <h3>Foto profil dan cover</h3>
                <p>Gunakan gambar hingga 5 MB. Foto cover vendor akan terlihat di hasil pencarian UMKM.</p>
                <div class="onboarding-image-fields">
                    <div class="auth-field">
                        <label for="profile_image">Foto profil</label>
                        @if ($profile->profile_image_path)
                            <img class="onboarding-image-preview onboarding-profile-preview" src="{{ route('business-profile.image', [$profile, 'profile']) }}" alt="Foto profil saat ini">
                        @endif
                        <input class="onboarding-image-input" id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="auth-field-hint">JPG, PNG, GIF, atau WebP. Mengunggah gambar baru akan mengganti foto saat ini.</small>
                        @error('profile_image')<span class="auth-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="auth-field">
                        <label for="cover_image">Foto cover</label>
                        @if ($profile->cover_image_path)
                            <img class="onboarding-image-preview onboarding-cover-preview" src="{{ route('business-profile.image', [$profile, 'cover']) }}" alt="Foto cover saat ini">
                        @endif
                        <input class="onboarding-image-input" id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="auth-field-hint">JPG, PNG, GIF, atau WebP. Mengunggah gambar baru akan mengganti cover saat ini.</small>
                        @error('cover_image')<span class="auth-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </section>
            <section class="onboarding-form-section onboarding-review-confirm">
                <h3><span>03</span> Tinjau profil</h3>
                <p>Periksa kembali profil usaha dan kebutuhan bisnismu sebelum menyimpan.</p>
                @error('confirm_profile')<span class="auth-field-error">{{ $message }}</span>@enderror
                <label class="auth-remember onboarding-confirm">
                    <input name="confirm_profile" type="checkbox" value="1" @checked(old('confirm_profile')) required>
                    <span>Saya sudah meninjau dan mengonfirmasi informasi di atas.</span>
                </label>
            </section>
            <button class="auth-submit" type="submit">Simpan profil <i class="bi bi-check2"></i></button>
        </form>

        @if (! $isCompleted)
            <form class="onboarding-logout" action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">Keluar dari akun ini</button>
            </form>
        @endif
    @else
        @php
            $reviewRows = [
                'Nama usaha' => $profile->business_name,
                'Bidang usaha' => $profile->category,
                'Lokasi' => $profile->location,
                ...($roleLabel === 'Vendor' ? ['Alamat lengkap usaha' => $profile->business_address] : []),
                'Nomor kontak' => $profile->phone,
                'Tentang usaha' => $profile->description,
            ];
            $detailRows = $roleLabel === 'Vendor'
                ? [
                    'Jenis kemasan atau layanan' => $profile->product_categories,
                    'Bahan' => $profile->materials,
                    'MOQ' => $profile->minimum_order_quantity ? number_format($profile->minimum_order_quantity, 0, ',', '.') . ' pcs' : null,
                    'Kapasitas per bulan' => $profile->monthly_capacity ? number_format($profile->monthly_capacity, 0, ',', '.') . ' pcs' : null,
                    'Waktu produksi' => $profile->production_lead_time_days ? $profile->production_lead_time_days . ' hari' : null,
                ]
                : [
                    'Jenis produk' => $profile->product_type,
                    'Jenis kemasan' => $profile->packaging_type,
                    'Bahan pilihan' => $profile->preferred_material,
                    'Kebutuhan per bulan' => $profile->monthly_quantity ? number_format($profile->monthly_quantity, 0, ',', '.') . ' pcs' : null,
                ];
        @endphp
        <div class="onboarding-review">
            @if ($profile->profile_image_path || $profile->cover_image_path)
                <section class="onboarding-review-section">
                    <h3>Foto profil dan cover</h3>
                    <div class="onboarding-image-fields">
                        @if ($profile->profile_image_path)
                            <div>
                                <span class="onboarding-review-image-label">Foto profil</span>
                                <img class="onboarding-image-preview onboarding-profile-preview" src="{{ route('business-profile.image', [$profile, 'profile']) }}" alt="Foto profil usaha">
                            </div>
                        @endif
                        @if ($profile->cover_image_path)
                            <div>
                                <span class="onboarding-review-image-label">Foto cover</span>
                                <img class="onboarding-image-preview onboarding-cover-preview" src="{{ route('business-profile.image', [$profile, 'cover']) }}" alt="Foto cover usaha">
                            </div>
                        @endif
                    </div>
                </section>
            @endif
            @foreach (['Profil usaha' => $reviewRows, 'Informasi ' . strtolower($roleLabel) => $detailRows] as $sectionTitle => $rows)
                <section class="onboarding-review-section">
                    <h3>{{ $sectionTitle }}</h3>
                    @foreach ($rows as $label => $value)
                        <div class="onboarding-review-row"><span>{{ $label }}</span><strong>{{ filled($value) ? $value : 'Belum diisi' }}</strong></div>
                    @endforeach
                </section>
            @endforeach
        </div>
        <a class="auth-submit onboarding-finish-link" href="{{ route('onboarding.step', ['step' => 1]) }}">Perbarui profil <i class="bi bi-pencil"></i></a>
        @if ($roleLabel === 'Vendor')
            <a class="auth-submit onboarding-finish-link" href="{{ route('vendor.verification.index') }}">Lengkapi verifikasi vendor <i class="bi bi-patch-check"></i></a>
        @endif
        <a class="auth-submit onboarding-finish-link" href="{{ route('workspace') }}">Kembali ke ruang kerja <i class="bi bi-arrow-right"></i></a>
    @endif

    @if ($isCompleted && ! $isEditing)
        <section class="account-delete-section">
            <h3>Hapus akun Navora</h3>
            <p>Akun dan profil usaha akan dihapus permanen. Masukkan kata sandi untuk mengonfirmasi tindakan ini.</p>
            <form action="{{ route('account.destroy') }}" method="POST" class="auth-form">
                @csrf
                @method('DELETE')
                <div class="auth-field">
                    <label for="delete-account-password">Kata sandi saat ini</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input id="delete-account-password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                </div>
                <button class="auth-submit account-delete-submit" type="submit">Hapus akun secara permanen</button>
            </form>
        </section>
    @endif

    <p class="auth-switch">Punya masukan tentang Navora atau vendor? <a href="{{ route('feedback.create') }}">Kirim masukan</a></p>
@endsection
