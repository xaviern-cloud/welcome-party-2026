<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Tambahkan panitia lain di sini, misalnya 'AD1' => 'Panitia 2'
        $admins = [
            'ADM' => 'Panitia Utama',
        ];

        foreach ($admins as $code => $name) {
            if (User::query()->whereKey($code)->exists()) {
                $this->command?->info("Admin {$code} sudah ada, dilewati (password tidak diubah).");
                continue;
            }

            $password = config('event.admin_password') ?: Str::password(12, symbols: false);

            $admin = new User();
            $admin->forceFill([
                'nim_code' => $code,
                'name'     => $name,
                'role'     => User::ROLE_ADMIN,
                'password' => $password, // di-hash otomatis oleh cast 'hashed'
            ])->save();

            $this->command?->warn("Admin {$code} dibuat. Password: {$password}  (simpan, tidak ditampilkan lagi)");
        }
    }
}
