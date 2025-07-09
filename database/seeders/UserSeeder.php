<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $kasirRole = DB::table('roles')->where('name', 'kasir')->first();

        $users = [
            [
                'name' => 'Admin Owner',
                'email' => 'admin@businessdashboard.com',
                'password' => Hash::make('admin123'),
                'phone' => '081234567890',
                'address' => 'Jakarta, Indonesia',
                'role' => 'admin',
                'role_id' => $adminRole->id,
                'permissions' => json_encode([
                    'dashboard' => ['view_all', 'analytics', 'kpi'],
                    'pos' => ['create', 'read', 'update', 'delete'],
                    'customers' => ['create', 'read', 'update', 'delete', 'analyze'],
                    'inventory' => ['create', 'read', 'update', 'delete', 'opname'],
                    'products' => ['create', 'read', 'update', 'delete', 'bulk_import'],
                    'categories' => ['create', 'read', 'update', 'delete'],
                    'financial' => ['view_all', 'analyze', 'export'],
                    'fund_allocation' => ['view', 'create', 'update', 'override'],
                    'reports' => ['view_all', 'export', 'schedule'],
                    'users' => ['create', 'read', 'update', 'delete'],
                    'settings' => ['view', 'update'],
                ]),
                'created_by' => null,
                'is_active' => true,
                'transaction_limit' => null,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kasir 1',
                'email' => 'kasir1@businessdashboard.com',
                'password' => Hash::make('kasir123'),
                'phone' => '081234567891',
                'address' => 'Jakarta, Indonesia',
                'role' => 'kasir',
                'role_id' => $kasirRole->id,
                'permissions' => json_encode([
                    'dashboard' => ['view_limited', 'sales_only'],
                    'pos' => ['create', 'read', 'update_own'],
                    'customers' => ['create', 'read', 'update_basic'],
                    'inventory' => ['read', 'alert'],
                    'products' => ['read', 'search'],
                    'categories' => ['read'],
                    'reports' => ['view_sales', 'export_limited'],
                    'users' => ['view_own_profile'],
                ]),
                'created_by' => 1,
                'is_active' => true,
                'transaction_limit' => 10000000, // 10 juta rupiah
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kasir 2',
                'email' => 'kasir2@businessdashboard.com',
                'password' => Hash::make('kasir123'),
                'phone' => '081234567892',
                'address' => 'Jakarta, Indonesia',
                'role' => 'kasir',
                'role_id' => $kasirRole->id,
                'permissions' => json_encode([
                    'dashboard' => ['view_limited', 'sales_only'],
                    'pos' => ['create', 'read', 'update_own'],
                    'customers' => ['create', 'read', 'update_basic'],
                    'inventory' => ['read', 'alert'],
                    'products' => ['read', 'search'],
                    'categories' => ['read'],
                    'reports' => ['view_sales', 'export_limited'],
                    'users' => ['view_own_profile'],
                ]),
                'created_by' => 1,
                'is_active' => true,
                'transaction_limit' => 5000000, // 5 juta rupiah
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('test123'),
                'phone' => '081234567893',
                'address' => 'Jakarta, Indonesia',
                'role' => 'kasir',
                'role_id' => $kasirRole->id,
                'permissions' => json_encode([
                    'dashboard' => ['view_limited', 'sales_only'],
                    'pos' => ['create', 'read', 'update_own'],
                    'customers' => ['create', 'read', 'update_basic'],
                    'inventory' => ['read', 'alert'],
                    'products' => ['read', 'search'],
                    'categories' => ['read'],
                    'reports' => ['view_sales', 'export_limited'],
                    'users' => ['view_own_profile'],
                ]),
                'created_by' => 1,
                'is_active' => true,
                'transaction_limit' => 1000000, // 1 juta rupiah
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);
    }
}