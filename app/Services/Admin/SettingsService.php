<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache; // Contoh penggunaan cache untuk setting

class SettingsService
{
    public function updateUserProfile(User $user, array $data): User
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        return $user;
    }

    public function getSystemSettings(): array
    {
        // Ambil setting dari cache atau database
        return Cache::remember('system_settings', 3600, function () {
            // Logika untuk mengambil dari tabel settings jika ada
            return [
                'app_name' => 'Sistem Bisnis Komprehensif',
                'maintenance_mode' => false,
            ];
        });
    }

    public function updateSystemSettings(array $data): void
    {
        // Logika untuk menyimpan settings ke database
        // ...

        // Perbarui cache
        Cache::put('system_settings', $data, 3600);
    }
}