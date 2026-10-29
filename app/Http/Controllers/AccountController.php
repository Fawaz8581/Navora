<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Masukkan kata sandi akun untuk melanjutkan.',
            'password.current_password' => 'Kata sandi yang dimasukkan tidak sesuai.',
        ]);

        $user = $request->user();
        $profile = $user->businessProfile;

        if ($profile && ! Storage::disk('local')->deleteDirectory('vendor-verification/'.$profile->id)) {
            return back()->withErrors([
                'account' => 'Dokumen verifikasi tidak berhasil dihapus. Akun kamu belum dihapus.',
            ]);
        }

        Auth::logout();

        if (! $user->delete()) {
            return back()->withErrors([
                'account' => 'Akun tidak berhasil dihapus. Silakan coba kembali.',
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Akun Navora kamu berhasil dihapus.');
    }
}
