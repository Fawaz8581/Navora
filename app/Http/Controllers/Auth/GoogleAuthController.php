<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', Rule::in(['umkm', 'vendor', 'partner'])],
        ]);
        $google = config('services.google');

        if (! filled($google['client_id'] ?? null)
            || ! filled($google['client_secret'] ?? null)
            || ! filled($google['redirect'] ?? null)) {
            return back()->with('status', 'Google sign-in belum aktif. Lengkapi kredensial Google OAuth di file .env terlebih dahulu.');
        }

        $state = Str::random(40);
        $request->session()->put([
            'google_oauth_state' => $state,
            'google_oauth_role' => $validated['role'] ?? null,
        ]);

        $query = http_build_query([
            'client_id' => $google['client_id'],
            'redirect_uri' => $google['redirect'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $role = $request->session()->pull('google_oauth_role');
        $providedState = $request->query('state');

        if (! is_string($expectedState)
            || ! is_string($providedState)
            || ! hash_equals($expectedState, $providedState)) {
            return redirect()->route('login')->with('status', 'Permintaan masuk Google tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');
        }

        if ($request->filled('error')) {
            return redirect()->route('login')->with('status', 'Proses masuk dengan Google dibatalkan. Kamu bisa mencoba lagi.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()->route('login')->with('status', 'Google tidak mengirim kode autentikasi. Silakan coba lagi.');
        }

        $google = config('services.google');

        if (! filled($google['client_id'] ?? null)
            || ! filled($google['client_secret'] ?? null)
            || ! filled($google['redirect'] ?? null)) {
            return redirect()->route('login')->with('status', 'Google sign-in belum dikonfigurasi. Lengkapi kredensial Google OAuth di file .env.');
        }

        try {
            $tokenResponse = Http::asForm()
                ->connectTimeout(3)
                ->timeout(8)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => $google['client_id'],
                    'client_secret' => $google['client_secret'],
                    'redirect_uri' => $google['redirect'],
                    'grant_type' => 'authorization_code',
                ]);
        } catch (ConnectionException) {
            Log::warning('Google OAuth token request could not connect.');

            return redirect()->route('login')->with('status', 'Layanan Google belum dapat dihubungi. Silakan coba lagi.');
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! $tokenResponse->successful() || ! is_string($accessToken) || $accessToken === '') {
            Log::warning('Google OAuth token exchange was rejected.', ['status' => $tokenResponse->status()]);

            return redirect()->route('login')->with('status', 'Google tidak menerima proses autentikasi. Periksa konfigurasi OAuth lalu coba lagi.');
        }

        try {
            $profile = Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            Log::warning('Google OAuth profile request failed.');

            return redirect()->route('login')->with('status', 'Profil Google tidak dapat dibaca. Silakan coba lagi.');
        }

        $googleId = is_array($profile) ? ($profile['sub'] ?? null) : null;
        $email = is_array($profile) ? ($profile['email'] ?? null) : null;
        $name = is_array($profile) ? ($profile['name'] ?? null) : null;

        if (! is_string($googleId)
            || $googleId === ''
            || ! is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ($profile['email_verified'] ?? false) !== true) {
            Log::warning('Google OAuth returned a profile without a valid verified email.');

            return redirect()->route('login')->with('status', 'Akun Google harus memiliki alamat email terverifikasi untuk digunakan di Navora.');
        }

        $email = mb_strtolower(trim($email));
        $user = User::where('google_id', $googleId)->first()
            ?? User::where('email', $email)->first();

        if ($user && $user->google_id !== null && ! hash_equals($user->google_id, $googleId)) {
            Log::warning('Google OAuth email is already linked to a different Google identity.', [
                'user_id' => $user->id,
            ]);

            return redirect()->route('login')->with('status', 'Email ini sudah terhubung ke akun Google lain. Masuk dengan metode yang sebelumnya digunakan.');
        }

        if (! $user) {
            $user = User::create([
                'name' => is_string($name) && trim($name) !== '' ? trim($name) : Str::before($email, '@'),
                'email' => $email,
                'role' => in_array($role, ['umkm', 'vendor', 'partner'], true) ? $role : 'umkm',
                'google_id' => $googleId,
                'password' => Str::random(64),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        } else {
            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('workspace'));
    }
}
