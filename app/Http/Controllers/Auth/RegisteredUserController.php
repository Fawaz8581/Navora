<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'profile' => new BusinessProfile,
            'role' => request()->string('role')->toString() === 'vendor' ? 'vendor' : 'umkm',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => mb_strtolower(trim($email))]);
        }

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['umkm', 'vendor'])],
            'password' => ['required', 'confirmed', 'max:255', Password::min(8)],
            'business_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'confirm_profile' => ['accepted'],
        ], $this->detailsRules($request->input('role'))), [
            'role.in' => 'Pilih peran yang tersedia.',
            'confirm_profile.accepted' => 'Tinjau dan konfirmasi profil sebelum membuat akun.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'password' => $validated['password'],
            ]);

            $user->businessProfile()->create(array_merge(
                Arr::except($validated, ['name', 'email', 'role', 'password', 'confirm_profile']),
                ['onboarding_step' => 3, 'completed_at' => now()],
            ));

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('business-profile.show')
            ->with('status', 'Akun dan profil usaha berhasil dibuat.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function detailsRules(mixed $role): array
    {
        if ($role === 'vendor') {
            return [
                'product_categories' => ['required', 'string', 'max:255'],
                'materials' => ['nullable', 'string', 'max:255'],
                'minimum_order_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
                'monthly_capacity' => ['nullable', 'integer', 'min:1', 'max:100000000'],
                'production_lead_time_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            ];
        }

        return [
            'product_type' => ['required', 'string', 'max:255'],
            'packaging_type' => ['nullable', 'string', 'max:255'],
            'preferred_material' => ['nullable', 'string', 'max:255'],
            'monthly_quantity' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
