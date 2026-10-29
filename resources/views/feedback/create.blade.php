@extends('auth.partials.layout')

@section('title', 'Kirim masukan')
@section('meta-description', 'Bantu Navora berkembang dengan berbagi pengalaman tentang platform dan vendor.')

@section('auth-form')
    <div class="auth-form-heading">
        <span class="auth-form-eyebrow"><span></span> SUARA PENGGUNA</span>
        <h2>Ceritakan pengalamanmu</h2>
        <p>Masukanmu tentang Navora maupun vendor membantu kami memperbaiki layanan. Masukan tersimpan dan dapat dibaca oleh tim Navora.</p>
    </div>

    @if (session('status'))
        <div class="auth-alert auth-alert-success" role="status">
            <i class="bi bi-check-circle"></i>{{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="auth-alert auth-alert-error" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <div>
                <strong>Masukan belum terkirim.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form action="{{ route('feedback.store') }}" method="POST" class="auth-form">
        @csrf
        <div class="auth-field">
            <label for="topic">Masukan tentang</label>
            <div class="auth-input-wrap auth-select-wrap">
                <i class="bi bi-chat-square-heart" aria-hidden="true"></i>
                <select id="topic" name="topic" required>
                    <option value="">Pilih topik</option>
                    <option value="navora" @selected(old('topic') === 'navora')>Platform Navora</option>
                    <option value="vendor" @selected(old('topic') === 'vendor')>Vendor di Navora</option>
                </select>
                <i class="bi bi-chevron-down auth-select-chevron" aria-hidden="true"></i>
            </div>
            @error('topic')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="vendor_name">Nama vendor <span class="onboarding-optional">Isi jika masukan terkait vendor</span></label>
            <div class="auth-input-wrap">
                <i class="bi bi-shop" aria-hidden="true"></i>
                <input id="vendor_name" name="vendor_name" type="text" value="{{ old('vendor_name') }}" maxlength="255" autocomplete="off" placeholder="Contoh: PouchKita Studio">
            </div>
            @error('vendor_name')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="rating">Penilaian (opsional)</label>
            <div class="auth-input-wrap auth-select-wrap">
                <i class="bi bi-star" aria-hidden="true"></i>
                <select id="rating" name="rating">
                    <option value="">Lewati penilaian</option>
                    @foreach (range(5, 1) as $rating)
                        <option value="{{ $rating }}" @selected((string) old('rating') === (string) $rating)>{{ $rating }} dari 5</option>
                    @endforeach
                </select>
                <i class="bi bi-chevron-down auth-select-chevron" aria-hidden="true"></i>
            </div>
            @error('rating')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="auth-field">
            <label for="message">Masukanmu</label>
            <textarea class="onboarding-textarea" id="message" name="message" rows="5" maxlength="3000" minlength="10" placeholder="Ceritakan bagian yang membantu, kendala yang kamu temui, atau perubahan yang kamu harapkan..." required>{{ old('message') }}</textarea>
            @error('message')<span class="auth-field-error">{{ $message }}</span>@enderror
        </div>
        <button class="auth-submit" type="submit">Kirim masukan <i class="bi bi-send"></i></button>
    </form>

    <p class="auth-switch"><a href="{{ route('workspace') }}">Kembali ke ruang Navora</a></p>
@endsection
