<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full access to all features and settings',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'kasir',
                'display_name' => 'Kasir',
                'description' => 'Limited access to POS and basic operations',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('roles')->insert($roles);
    }
}