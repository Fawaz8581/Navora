<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Masukkan alamat email.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'password.required' => 'Masukkan kata sandi.',
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau kata sandi tidak cocok.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()->role === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        }

        if (in_array($request->user()->role, ['umkm', 'vendor'], true)
            && ! $request->user()->businessProfile?->completed_at) {
            return redirect()->route('onboarding.index');
        }

        return redirect()->intended(route('workspace'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Kamu berhasil keluar dari akun Navora.');
    }
}
