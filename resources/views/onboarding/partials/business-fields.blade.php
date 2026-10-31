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
