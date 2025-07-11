<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seeder untuk mengisi database aplikasi.
     */
    public function run(): void
    {
        // Urutan pemanggilan seeder sangat penting untuk menjaga integritas data (foreign key).
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,       // Seeder baru yang lebih baik
            UserSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            ExpenseCategorySeeder::class,
            FundAllocationSeeder::class,
        ]);
    }
}