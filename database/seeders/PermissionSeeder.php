<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            [
                'name' => 'create_user',
                'display_name' => 'Buat User',
                'description' => 'Dapat membuat user baru',
                'module' => 'user_management',
                'action' => 'create'
            ],
            [
                'name' => 'read_user',
                'display_name' => 'Lihat User',
                'description' => 'Dapat melihat daftar user',
                'module' => 'user_management'
            ],
            [
                'name' => 'update_user',
                'display_name' => 'Edit User',
                'description' => 'Dapat mengedit user',
                'module' => 'user_management'
            ],
            [
                'name' => 'delete_user',
                'display_name' => 'Hapus User',
                'description' => 'Dapat menghapus user',
                'module' => 'user_management'
            ],

            // Product Management
            [
                'name' => 'create_product',
                'display_name' => 'Buat Produk',
                'description' => 'Dapat membuat produk baru',
                'module' => 'product_management'
            ],
            [
                'name' => 'read_product',
                'display_name' => 'Lihat Produk',
                'description' => 'Dapat melihat daftar produk',
                'module' => 'product_management'
            ],
            [
                'name' => 'update_product',
                'display_name' => 'Edit Produk',
                'description' => 'Dapat mengedit produk',
                'module' => 'product_management'
            ],
            [
                'name' => 'delete_product',
                'display_name' => 'Hapus Produk',
                'description' => 'Dapat menghapus produk',
                'module' => 'product_management'
            ],

            // Transaction Management
            [
                'name' => 'create_transaction',
                'display_name' => 'Buat Transaksi',
                'description' => 'Dapat membuat transaksi baru',
                'module' => 'transaction_management'
            ],
            [
                'name' => 'read_transaction',
                'display_name' => 'Lihat Transaksi',
                'description' => 'Dapat melihat daftar transaksi',
                'module' => 'transaction_management'
            ],
            [
                'name' => 'update_transaction',
                'display_name' => 'Edit Transaksi',
                'description' => 'Dapat mengedit transaksi',
                'module' => 'transaction_management'
            ],
            [
                'name' => 'delete_transaction',
                'display_name' => 'Hapus Transaksi',
                'description' => 'Dapat menghapus transaksi',
                'module' => 'transaction_management'
            ],

            // Inventory Management
            [
                'name' => 'manage_inventory',
                'display_name' => 'Kelola Inventori',
                'description' => 'Dapat mengelola inventori',
                'module' => 'inventory_management'
            ],
            [
                'name' => 'view_inventory',
                'display_name' => 'Lihat Inventori',
                'description' => 'Dapat melihat inventori',
                'module' => 'inventory_management'
            ],

            // Report Management
            [
                'name' => 'view_reports',
                'display_name' => 'Lihat Laporan',
                'description' => 'Dapat melihat laporan',
                'module' => 'report_management'
            ],
            [
                'name' => 'export_reports',
                'display_name' => 'Ekspor Laporan',
                'description' => 'Dapat mengekspor laporan',
                'module' => 'report_management'
            ],

            // Financial Management
            [
                'name' => 'manage_cash_flow',
                'display_name' => 'Kelola Kas',
                'description' => 'Dapat mengelola cash flow',
                'module' => 'financial_management'
            ],
            [
                'name' => 'view_profits',
                'display_name' => 'Lihat Keuntungan',
                'description' => 'Dapat melihat keuntungan',
                'module' => 'financial_management'
            ],

            // Settings
            [
                'name' => 'manage_settings',
                'display_name' => 'Kelola Pengaturan',
                'description' => 'Dapat mengelola pengaturan sistem',
                'module' => 'settings'
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}