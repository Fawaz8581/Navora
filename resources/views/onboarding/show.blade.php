@extends('auth.partials.layout')

@section('title', $isCompleted ? 'Profil usaha' : 'Lengkapi profil usaha')
@section('meta-description', 'Lengkapi profil usaha Navora agar pencarian vendor dan kebutuhan kemasan lebih sesuai.')

@section('auth-form')
    @php
        $roleLabel = Auth::user()->role === 'vendor' ? 'Vendor' : 'UMKM';
        $stepTitles = ['Profil usaha', 'Kebutuhan bisnis', 'Tinjau profil'];
    @endphp

    <div class="auth-form-heading onboarding-heading">
        <span class="auth-form-eyebrow"><span></span> {{ $isCompleted ? 'PROFIL USAHA' : 'PERSIAPAN AKUN · ' . $roleLabel }}</span>
        <h2>{{ $isCompleted ? 'Profil usahamu' : ($step === 3 ? 'Periksa kembali' : $stepTitles[$step - 1]) }}</h2>
        <p>{{ $isCompleted ? 'Data profil tersimpan di akun Navora dan dapat kamu perbarui kapan saja.' : 'Lengkapi beberapa informasi agar Navora dapat menyesuaikan ruang kerjamu.' }}</p>
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

    @if (! $isCompleted)
        <div class="onboarding-progress" aria-label="Langkah onboarding">
            @foreach ($stepTitles as $index => $title)
                @php
                    $stepNumber = $index + 1;
                @endphp
                <a class="onboarding-progress-step {{ $step === $stepNumber ? 'is-current' : ($step > $stepNumber ? 'is-done' : '') }}"
                    @if ($stepNumber < $step) href="{{ route('onboarding.step', ['step' => $stepNumber]) }}" @endif
                    @if ($step === $stepNumber) aria-current="step" @endif>
                    <span>{{ $stepNumber < $step ? '✓' : $stepNumber }}</span>
                    <small>{{ $title }}</small>
                </a>
            @endforeach
        </div>
    @endif

    @if ($step === 1)
        <form action="{{ route('onboarding.update', ['step' => 1]) }}" method="post" class="auth-form">
            @csrf
            <div class="auth-field">
                <label for="business_name">Nama usaha</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-shop" aria-hidden="true"></i>
                    <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $profile->business_name) }}" maxlength="255" autocomplete="organization" required autofocus>
                </div>
                @error('business_name')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="category">Bidang usaha</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <input id="category" name="category" type="text" value="{{ old('category', $profile->category) }}" placeholder="Contoh: makanan dan minuman" maxlength="255" required>
                </div>
                @error('category')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="location">Lokasi usaha</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <input id="location" name="location" type="text" value="{{ old('location', $profile->location) }}" placeholder="Kota atau kabupaten" maxlength="255" autocomplete="address-level2" required>
                </div>
                @error('location')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="phone">Nomor kontak <span class="onboarding-optional">Opsional</span></label>
                <div class="auth-input-wrap">
                    <i class="bi bi-telephone" aria-hidden="true"></i>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $profile->phone) }}" maxlength="40" autocomplete="tel">
                </div>
                @error('phone')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="description">Tentang usaha <span class="onboarding-optional">Opsional</span></label>
                <textarea class="onboarding-textarea" id="description" name="description" rows="3" maxlength="2000" placeholder="Ceritakan singkat produk atau layanan usahamu.">{{ old('description', $profile->description) }}</textarea>
                @error('description')<span class="auth-field-error">{{ $message }}</span>@enderror
            </div>
            <button class="auth-submit" type="submit">Lanjutkan <i class="bi bi-arrow-right"></i></button>
        </form>
    @elseif ($step === 2)
        <form action="{{ route('onboarding.update', ['step' => 2]) }}" method="post" class="auth-form">
            @csrf
            @if ($roleLabel === 'Vendor')
                <div class="auth-field">
                    <label for="product_categories">Jenis kemasan atau layanan</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                        <input id="product_categories" name="product_categories" type="text" value="{{ old('product_categories', $profile->product_categories) }}" placeholder="Contoh: pouch, botol, maklon" maxlength="255" required autofocus>
                    </div>
                    @error('product_categories')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="materials">Bahan yang tersedia <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-layers" aria-hidden="true"></i>
                        <input id="materials" name="materials" type="text" value="{{ old('materials', $profile->materials) }}" placeholder="Contoh: kraft, PET, aluminium" maxlength="255">
                    </div>
                    @error('materials')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="minimum_order_quantity">Minimum order quantity (MOQ)</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-boxes" aria-hidden="true"></i>
                        <input id="minimum_order_quantity" name="minimum_order_quantity" type="number" value="{{ old('minimum_order_quantity', $profile->minimum_order_quantity) }}" min="1" max="100000000" placeholder="Jumlah minimum dalam pcs" required>
                    </div>
                    @error('minimum_order_quantity')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="monthly_capacity">Kapasitas produksi per bulan <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-speedometer2" aria-hidden="true"></i>
                        <input id="monthly_capacity" name="monthly_capacity" type="number" value="{{ old('monthly_capacity', $profile->monthly_capacity) }}" min="1" max="100000000" placeholder="Jumlah dalam pcs">
                    </div>
                    @error('monthly_capacity')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="production_lead_time_days">Waktu produksi dalam hari <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <input id="production_lead_time_days" name="production_lead_time_days" type="number" value="{{ old('production_lead_time_days', $profile->production_lead_time_days) }}" min="1" max="365" placeholder="Contoh: 14">
                    </div>
                    @error('production_lead_time_days')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
            @else
                <div class="auth-field">
                    <label for="product_type">Produk yang kamu buat</label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-basket" aria-hidden="true"></i>
                        <input id="product_type" name="product_type" type="text" value="{{ old('product_type', $profile->product_type) }}" placeholder="Contoh: kopi, sambal, atau skincare" maxlength="255" required autofocus>
                    </div>
                    @error('product_type')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="packaging_type">Jenis kemasan yang dicari <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-box" aria-hidden="true"></i>
                        <input id="packaging_type" name="packaging_type" type="text" value="{{ old('packaging_type', $profile->packaging_type) }}" placeholder="Contoh: pouch atau botol" maxlength="255">
                    </div>
                    @error('packaging_type')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="preferred_material">Bahan pilihan <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-layers" aria-hidden="true"></i>
                        <input id="preferred_material" name="preferred_material" type="text" value="{{ old('preferred_material', $profile->preferred_material) }}" placeholder="Contoh: kertas kraft atau PET" maxlength="255">
                    </div>
                    @error('preferred_material')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="auth-field">
                    <label for="monthly_quantity">Perkiraan kebutuhan per bulan <span class="onboarding-optional">Opsional</span></label>
                    <div class="auth-input-wrap">
                        <i class="bi bi-boxes" aria-hidden="true"></i>
                        <input id="monthly_quantity" name="monthly_quantity" type="number" value="{{ old('monthly_quantity', $profile->monthly_quantity) }}" min="1" max="100000000" placeholder="Jumlah dalam pcs">
                    </div>
                    @error('monthly_quantity')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
            @endif
            <p class="auth-privacy-note"><i class="bi bi-info-circle"></i> Data ini membantu menyesuaikan pengalamanmu. Informasi opsional dapat dilengkapi nanti.</p>
            <button class="auth-submit" type="submit">Lanjutkan <i class="bi bi-arrow-right"></i></button>
        </form>
    @else
        @php
            $reviewRows = [
                'Nama usaha' => $profile->business_name,
                'Bidang usaha' => $profile->category,
                'Lokasi' => $profile->location,
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
            @foreach (['Profil usaha' => $reviewRows, 'Informasi ' . strtolower($roleLabel) => $detailRows] as $sectionTitle => $rows)
                <section class="onboarding-review-section">
                    <div class="onboarding-review-heading">
                        <h3>{{ $sectionTitle }}</h3>
                        <a href="{{ route('onboarding.step', ['step' => $sectionTitle === 'Profil usaha' ? 1 : 2]) }}">Ubah</a>
                    </div>
                    @foreach ($rows as $label => $value)
                        <div class="onboarding-review-row"><span>{{ $label }}</span><strong>{{ filled($value) ? $value : 'Belum diisi' }}</strong></div>
                    @endforeach
                </section>
            @endforeach
        </div>
        @if ($isCompleted)
            @if ($roleLabel === 'Vendor')
                <a class="auth-submit onboarding-finish-link" href="{{ route('vendor.verification.index') }}">Lengkapi verifikasi vendor <i class="bi bi-patch-check"></i></a>
            @endif
            <a class="auth-submit onboarding-finish-link" href="{{ route('workspace') }}">Kembali ke ruang kerja <i class="bi bi-arrow-right"></i></a>
        @else
            <form action="{{ route('onboarding.update', ['step' => 3]) }}" method="post" class="auth-form onboarding-confirm-form">
                @csrf
                <label class="auth-remember onboarding-confirm"><input name="confirm_profile" type="checkbox" value="1" required><span>Saya sudah meninjau dan mengonfirmasi informasi profil ini.</span></label>
                <button class="auth-submit" type="submit">Simpan profil <i class="bi bi-check2"></i></button>
            </form>
        @endif
    @endif

    @if (! $isCompleted && $step > 1)
        <a class="onboarding-back-link" href="{{ route('onboarding.step', ['step' => $step - 1]) }}"><i class="bi bi-arrow-left"></i> Kembali ke langkah sebelumnya</a>
    @elseif (! $isCompleted && $step === 1)
        <form class="onboarding-logout" action="{{ route('logout') }}" method="post">
            @csrf
            <button type="submit">Keluar dari akun ini</button>
        </form>
    @endif

    @if (! $isCompleted)
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
