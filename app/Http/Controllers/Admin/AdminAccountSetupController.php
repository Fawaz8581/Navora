<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAccountSetupController extends Controller
{
    public function create(Request $request): View
    {
        $this->ensureSetupIsAvailable($request);

        $displayEmail = trim((string) config('services.navora_admin.email'));
        $email = mb_strtolower($displayEmail);
        $password = config('services.navora_admin.password');
        $admin = User::query()->where('email', $email)->first();

        if ($admin && $admin->role !== 'admin') {
            abort(409, 'Email admin Navora sudah digunakan oleh akun non-admin.');
        }

        $wasCreated = $admin === null;

        if ($wasCreated) {
            User::create([
                'name' => 'Navora',
                'email' => $email,
                'role' => 'admin',
                'password' => $password,
                'email_verified_at' => now(),
            ]);
        }

        return view('admin.setup-account', [
            'email' => $displayEmail,
            'wasCreated' => $wasCreated,
        ]);
    }

    private function ensureSetupIsAvailable(Request $request): void
    {
        abort_unless(
            app()->environment(['local', 'testing'])
                && in_array($request->ip(), ['127.0.0.1', '::1'], true),
            404,
        );

        $email = mb_strtolower(trim((string) config('services.navora_admin.email')));
        abort_if($email === '', 503, 'Konfigurasi email admin belum tersedia.');
        $password = config('services.navora_admin.password');
        abort_if(! is_string($password) || mb_strlen($password) < 8, 503, 'Konfigurasi password admin belum tersedia.');
    }
}
