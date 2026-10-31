@extends('auth.partials.layout')

@section('title', 'Buat akun')
@section('meta-description', 'Buat akun Navora dan lengkapi profil usaha dalam satu langkah.')
@section('auth-form-class', 'onboarding-form-wrap')

@section('auth-form')
    @php
        $role = old('role', $role) === 'vendor' ? 'vendor' : 'umkm';
        $profileErrors = $errors->hasAny([
            'business_name',
            'phone',
            'category',
            'location',
            'description',
            'product_type',
            'packaging_type',
            'preferred_material',
            'monthly_quantity',
            'product_categories',
            'materials',
            'minimum_order_quantity',
            'monthly_capacity',
            'production_lead_time_days',
            'confirm_profile',
        ]);
    @endphp

    <div class="auth-form-heading onboarding-heading">
        <span class="auth-form-eyebrow"><span></span> MULAI LANGKAH BARUMU</span>
        <h2 data-register-title>Buat akun Navora</h2>
        <p data-register-description>Buat akun Navora terlebih dahulu, lalu lengkapi profil usaha dan kebutuhan bisnismu.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-info" role="status"><i class="bi bi-info-circle"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Periksa kembali data pendaftaranmu.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="onboarding-register-progress" aria-live="polite">
        <span data-register-progress>{{ $profileErrors ? 'Langkah 2 dari 2' : 'Langkah 1 dari 2' }}</span>
    </div>

    <form action="{{ route('register.store') }}" method="post" class="auth-form onboarding-form" data-initial-step="{{ $profileErrors ? 2 : 1 }}">
        @csrf
        <fieldset class="onboarding-register-step" data-register-step="1">
            <legend class="visually-hidden">Akun Navora</legend>
            <section class="onboarding-form-section">
                <h3>Akun Navora</h3>
            <div class="auth-field">
                <label for="role">Saya bergabung sebagai</label>
                <div class="auth-input-wrap auth-select-wrap">
                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                    <select id="role" name="role" required aria-describedby="@error('role') role-error @enderror">
                        <option value="umkm" @selected($role === 'umkm')>Pemilik brand / UMKM</option>
                        <option value="vendor" @selected($role === 'vendor')>Vendor kemasan atau maklon</option>
                    </select>
                    <i class="bi bi-chevron-down auth-select-chevron" aria-hidden="true"></i>
                </div>
                @error('role')<span class="auth-field-error" id="role-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="name">Nama lengkap atau nama usaha</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Contoh: Dinda / Kopi Nusantara" autocomplete="name" maxlength="255" required autofocus aria-describedby="@error('name') name-error @enderror">
                </div>
                @error('name')<span class="auth-field-error" id="name-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="email">Email aktif</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="nama@contoh.com" autocomplete="email" maxlength="255" required aria-describedby="@error('email') email-error @enderror">
                </div>
                @error('email')<span class="auth-field-error" id="email-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="password">Buat kata sandi</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <input id="password" name="password" type="password" placeholder="Minimal 8 karakter" autocomplete="new-password" minlength="8" required aria-describedby="@error('password') password-error @enderror">
                </div>
                @error('password')<span class="auth-field-error" id="password-error">{{ $message }}</span>@enderror
            </div>
            <div class="auth-field">
                <label for="password_confirmation">Ulangi kata sandi</label>
                <div class="auth-input-wrap">
                    <i class="bi bi-lock-fill" aria-hidden="true"></i>
                    <input id="password_confirmation" name="password_confirmation" type="password" placeholder="Ketik ulang kata sandi" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
            </section>
            <button class="auth-submit" type="button" data-register-next>Next <i class="bi bi-arrow-right"></i></button>
        </fieldset>

        <fieldset class="onboarding-register-step" data-register-step="2" hidden disabled>
            <legend class="visually-hidden">Profil usaha dan kebutuhan bisnis</legend>
            @include('onboarding.partials.business-fields', ['sectionNumber' => ''])
            <div class="onboarding-review-confirm">
                <p>Tinjau kembali informasi sebelum membuat akun.</p>
                @error('confirm_profile')<span class="auth-field-error">{{ $message }}</span>@enderror
                <label class="auth-remember onboarding-confirm">
                    <input name="confirm_profile" type="checkbox" value="1" @checked(old('confirm_profile')) required>
                    <span>Saya sudah meninjau dan mengonfirmasi informasi di atas.</span>
                </label>
            </div>
            <div class="onboarding-register-actions">
                <button class="onboarding-register-back" type="button" data-register-back><i class="bi bi-arrow-left"></i> Kembali</button>
                <button class="auth-submit" type="submit">Buat akun <i class="bi bi-arrow-right"></i></button>
            </div>
        </fieldset>
    </form>

    <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>

    <script>
        const roleSelect = document.getElementById('role');
        const roleFields = document.querySelectorAll('[data-role-fields]');
        const registerForm = document.querySelector('[data-initial-step]');
        const registerSteps = document.querySelectorAll('[data-register-step]');
        const progress = document.querySelector('[data-register-progress]');
        const title = document.querySelector('[data-register-title]');
        const description = document.querySelector('[data-register-description]');

        const updateRoleFields = () => {
            roleFields.forEach((fields) => {
                const isActive = fields.dataset.roleFields === roleSelect.value;

                fields.disabled = !isActive;
                fields.hidden = !isActive;
            });
        };

        const showStep = (step) => {
            registerSteps.forEach((section) => {
                const isActive = Number(section.dataset.registerStep) === step;

                section.hidden = !isActive;
                section.disabled = Number(section.dataset.registerStep) === 2 && !isActive;

                if (Number(section.dataset.registerStep) === 1) {
                    section.querySelectorAll('input, select').forEach((input) => {
                        input.required = step === 1 && input.dataset.registerRequired === 'true';
                    });
                }
            });

            progress.textContent = `Langkah ${step} dari 2`;
            title.textContent = step === 1 ? 'Buat akun Navora' : 'Profil usaha dan kebutuhan bisnis';
            description.textContent = step === 1
                ? 'Buat akun Navora terlebih dahulu, lalu lengkapi profil usaha dan kebutuhan bisnismu.'
                : 'Lengkapi profil usaha dan kebutuhan bisnismu, lalu tinjau sebelum membuat akun.';
        };

        document.querySelector('[data-register-next]').addEventListener('click', () => {
            const accountStep = document.querySelector('[data-register-step="1"]');

            if (!accountStep.querySelector(':invalid')) {
                showStep(2);
                document.getElementById('business_name').focus();
                return;
            }

            accountStep.querySelector(':invalid').reportValidity();
        });

        document.querySelector('[data-register-back]').addEventListener('click', () => {
            showStep(1);
            document.getElementById('name').focus();
        });

        roleSelect.addEventListener('change', updateRoleFields);
        document.querySelectorAll('[data-register-step="1"] input, [data-register-step="1"] select').forEach((input) => {
            input.dataset.registerRequired = input.required ? 'true' : 'false';
        });
        updateRoleFields();
        showStep(Number(registerForm.dataset.initialStep));
    </script>
@endsection
