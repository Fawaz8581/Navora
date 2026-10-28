<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(bool $resetExisting = true): void
    {
        $password = Str::password(48);
        $admin = User::where('email', 'admin@navora.local')->first();

        if ($admin && $admin->role !== 'admin') {
            throw new LogicException('The reserved Navora admin email already belongs to a non-admin account.');
        }

        if ($admin && ! $resetExisting) {
            if (isset($this->command)) {
                $this->command->info('Akun admin Navora sudah tersedia; kata sandi yang ada tidak diubah.');
            }

            return;
        }

        if ($admin) {
            $admin->forceFill(['password' => $password])->save();
        } else {
            $admin = User::create([
                'name' => 'Navora',
                'email' => 'admin@navora.local',
                'role' => 'admin',
                'password' => $password,
                'email_verified_at' => now(),
            ]);
        }

        if (isset($this->command)) {
            $this->command->info($admin->wasRecentlyCreated
                ? 'Akun admin Navora berhasil dibuat. Simpan kredensial berikut:'
                : 'Kata sandi akun admin Navora telah diatur ulang. Simpan kredensial baru berikut:');
            $this->command->line('Email: '.$admin->email);
            $this->command->line('Password: '.$password);
        }
    }
}
