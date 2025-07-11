<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Menjalankan seeder untuk peran (roles).
     */
    public function run(): void
    {
        // Kosongkan tabel terlebih dahulu untuk menghindari duplikasi
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Role::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $roles = config('roles'); // Mengambil data dari config/roles.php

        foreach ($roles as $roleName => $roleData) {
            Role::create([
                'name' => $roleName,
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);
        }
    }
}