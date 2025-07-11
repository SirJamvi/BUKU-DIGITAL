<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Menjalankan seeder untuk pengguna (users).
     */
    public function run(): void
    {
        // Dapatkan peran dari database
        $adminRole = Role::where('name', 'admin')->first();
        $kasirRole = Role::where('name', 'kasir')->first();

        // Buat Admin Utama
        $admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin@businessdashboard.com',
            'password' => bcrypt('admin123'),
            'role' => 'admin',
        ]);
        $admin->roles()->attach($adminRole);

        // Buat Kasir 1
        $kasir1 = User::factory()->create([
            'name' => 'Kasir Satu',
            'email' => 'kasir1@businessdashboard.com',
            'password' => bcrypt('kasir123'),
            'role' => 'kasir',
            'created_by' => $admin->id,
            'transaction_limit' => 10000000,
        ]);
        $kasir1->roles()->attach($kasirRole);

        // Buat Kasir 2
        $kasir2 = User::factory()->create([
            'name' => 'Kasir Dua',
            'email' => 'kasir2@businessdashboard.com',
            'password' => bcrypt('kasir123'),
            'role' => 'kasir',
            'created_by' => $admin->id,
            'transaction_limit' => 5000000,
        ]);
        $kasir2->roles()->attach($kasirRole);
    }
}