<section class="onboarding-form-section">
    <h3>@if (filled($sectionNumber ?? null))<span>{{ $sectionNumber }}</span> @endif{{ 'Profil usaha dan kebutuhan bisnis' }}</h3>
    <h4 class="onboarding-form-subheading">Profil usaha</h4>
    <div class="auth-field">
        <label for="business_name">Nama usaha</label>
        <div class="auth-input-wrap">
            <i class="bi bi-shop" aria-hidden="true"></i>
            <input id="business_name" name="business_name" type="text" value="{{ old('business_name', $profile->business_name) }}" maxlength="255" autocomplete="organization" required>
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
    @if ($role === 'vendor')
        <div class="auth-field">
            <label for="business_address">Alamat lengkap usaha</label>
            <textarea class="onboarding-textarea" id="business_address" name="business_address" rows="3" maxlength="1000" autocomplete="street-address" placeholder="Jalan, nomor, kelurahan, kecamatan, kota, dan kode pos" required>{{ old('business_address', $profile->business_address) }}</textarea>
            <small>Alamat ini menjadi alamat asal vendor. UMKM hanya perlu memasukkan alamat tujuan pesanan.</small>
            @error('business_address')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field" data-location-picker>
            <label for="rajaongkir_area_name">Area asal pengiriman</label>
            <div class="auth-input-wrap">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <input id="rajaongkir_area_name" name="rajaongkir_area_name" type="text"
                    value="{{ old('rajaongkir_area_name', $profile->rajaongkir_area_name) }}"
                    placeholder="Cari kecamatan atau kota" maxlength="255"
                    data-rajaongkir-location autocomplete="off" required>
            </div>
            <input type="hidden" name="rajaongkir_area_id"
                value="{{ old('rajaongkir_area_id', $profile->rajaongkir_area_id) }}"
                data-rajaongkir-area-id>
            <small>Pilih hasil lokasi agar ongkir dapat dihitung. Tarif memakai ID area RajaOngkir.</small>
            @error('rajaongkir_area_id')<span class="auth-field-error">{{ $message }}</span>@enderror
            @error('rajaongkir_area_name')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
    @endif
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
    <h4 class="onboarding-form-subheading">Kebutuhan bisnis</h4>
    <fieldset class="onboarding-role-fields" data-role-fields="umkm" @disabled($role !== 'umkm') @if($role !== 'umkm') hidden @endif>
        <legend class="visually-hidden">Kebutuhan bisnis UMKM</legend>
        <div class="auth-field">
            <label for="product_type">Produk yang kamu buat</label>
            <div class="auth-input-wrap">
                <i class="bi bi-basket" aria-hidden="true"></i>
                <input id="product_type" name="product_type" type="text" value="{{ old('product_type', $profile->product_type) }}" placeholder="Contoh: kopi, sambal, atau skincare" maxlength="255" required>
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
    </fieldset>
    <fieldset class="onboarding-role-fields" data-role-fields="vendor" @disabled($role !== 'vendor') @if($role !== 'vendor') hidden @endif>
        <legend class="visually-hidden">Kebutuhan bisnis vendor</legend>
        <div class="auth-field">
            <label for="product_categories">Jenis kemasan atau layanan</label>
            <div class="auth-input-wrap">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <input id="product_categories" name="product_categories" type="text" value="{{ old('product_categories', $profile->product_categories) }}" placeholder="Contoh: pouch, botol, maklon" maxlength="255" required>
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
            <label for="indicative_price_per_unit">Harga indikatif per pcs <span class="onboarding-optional">Opsional</span></label>
            <div class="auth-input-wrap">
                <i class="bi bi-cash" aria-hidden="true"></i>
                <input id="indicative_price_per_unit" name="indicative_price_per_unit" type="number" value="{{ old('indicative_price_per_unit', $profile->indicative_price_per_unit) }}" min="0.01" max="1000000000" step="0.01" placeholder="Harga awal, bukan penawaran final">
            </div>
            @error('indicative_price_per_unit')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label>Titik lokasi usaha <span class="onboarding-optional">Opsional untuk estimasi jarak</span></label>
            <div class="auth-form-grid">
                <div>
                    <label class="visually-hidden" for="latitude">Latitude</label>
                    <input class="form-control" id="latitude" name="latitude" type="number" value="{{ old('latitude', $profile->latitude) }}" min="-90" max="90" step="0.0000001" placeholder="Latitude">
                    @error('latitude')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
                <div>
                    <label class="visually-hidden" for="longitude">Longitude</label>
                    <input class="form-control" id="longitude" name="longitude" type="number" value="{{ old('longitude', $profile->longitude) }}" min="-180" max="180" step="0.0000001" placeholder="Longitude">
                    @error('longitude')<span class="auth-field-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <small>Koordinat digunakan untuk memperkirakan jarak garis lurus dalam pencarian vendor.</small>
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
    </fieldset>
</section>
