<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard Permissions
            ['module' => 'dashboard', 'action' => 'view_all', 'description' => 'View all dashboard analytics'],
            ['module' => 'dashboard', 'action' => 'view_limited', 'description' => 'View limited dashboard data'],
            ['module' => 'dashboard', 'action' => 'analytics', 'description' => 'Access analytics features'],
            ['module' => 'dashboard', 'action' => 'kpi', 'description' => 'View KPI metrics'],
            ['module' => 'dashboard', 'action' => 'sales_only', 'description' => 'View sales data only'],

            // POS Permissions
            ['module' => 'pos', 'action' => 'create', 'description' => 'Create transactions'],
            ['module' => 'pos', 'action' => 'read', 'description' => 'View transactions'],
            ['module' => 'pos', 'action' => 'update', 'description' => 'Update transactions'],
            ['module' => 'pos', 'action' => 'delete', 'description' => 'Delete transactions'],
            ['module' => 'pos', 'action' => 'update_own', 'description' => 'Update own transactions only'],

            // Customer Permissions
            ['module' => 'customers', 'action' => 'create', 'description' => 'Create customers'],
            ['module' => 'customers', 'action' => 'read', 'description' => 'View customers'],
            ['module' => 'customers', 'action' => 'update', 'description' => 'Update customers'],
            ['module' => 'customers', 'action' => 'delete', 'description' => 'Delete customers'],
            ['module' => 'customers', 'action' => 'analyze', 'description' => 'Analyze customer data'],
            ['module' => 'customers', 'action' => 'update_basic', 'description' => 'Basic customer updates'],

            // Inventory Permissions
            ['module' => 'inventory', 'action' => 'create', 'description' => 'Create inventory items'],
            ['module' => 'inventory', 'action' => 'read', 'description' => 'View inventory'],
            ['module' => 'inventory', 'action' => 'update', 'description' => 'Update inventory'],
            ['module' => 'inventory', 'action' => 'delete', 'description' => 'Delete inventory'],
            ['module' => 'inventory', 'action' => 'opname', 'description' => 'Perform stock opname'],
            ['module' => 'inventory', 'action' => 'alert', 'description' => 'View stock alerts'],

            // Product Permissions
            ['module' => 'products', 'action' => 'create', 'description' => 'Create products'],
            ['module' => 'products', 'action' => 'read', 'description' => 'View products'],
            ['module' => 'products', 'action' => 'update', 'description' => 'Update products'],
            ['module' => 'products', 'action' => 'delete', 'description' => 'Delete products'],
            ['module' => 'products', 'action' => 'bulk_import', 'description' => 'Bulk import products'],
            ['module' => 'products', 'action' => 'search', 'description' => 'Search products'],

            // Category Permissions
            ['module' => 'categories', 'action' => 'create', 'description' => 'Create categories'],
            ['module' => 'categories', 'action' => 'read', 'description' => 'View categories'],
            ['module' => 'categories', 'action' => 'update', 'description' => 'Update categories'],
            ['module' => 'categories', 'action' => 'delete', 'description' => 'Delete categories'],

            // Financial Permissions
            ['module' => 'financial', 'action' => 'view_all', 'description' => 'View all financial data'],
            ['module' => 'financial', 'action' => 'analyze', 'description' => 'Analyze financial data'],
            ['module' => 'financial', 'action' => 'export', 'description' => 'Export financial reports'],
            ['module' => 'financial', 'action' => 'denied', 'description' => 'Access denied'],

            // Fund Allocation Permissions
            ['module' => 'fund_allocation', 'action' => 'view', 'description' => 'View fund allocation'],
            ['module' => 'fund_allocation', 'action' => 'create', 'description' => 'Create fund allocation'],
            ['module' => 'fund_allocation', 'action' => 'update', 'description' => 'Update fund allocation'],
            ['module' => 'fund_allocation', 'action' => 'override', 'description' => 'Override fund allocation'],
            ['module' => 'fund_allocation', 'action' => 'denied', 'description' => 'Access denied'],

            // Report Permissions
            ['module' => 'reports', 'action' => 'view_all', 'description' => 'View all reports'],
            ['module' => 'reports', 'action' => 'export', 'description' => 'Export reports'],
            ['module' => 'reports', 'action' => 'schedule', 'description' => 'Schedule reports'],
            ['module' => 'reports', 'action' => 'view_sales', 'description' => 'View sales reports'],
            ['module' => 'reports', 'action' => 'export_limited', 'description' => 'Limited export access'],

            // User Management Permissions
            ['module' => 'users', 'action' => 'create', 'description' => 'Create users'],
            ['module' => 'users', 'action' => 'read', 'description' => 'View users'],
            ['module' => 'users', 'action' => 'update', 'description' => 'Update users'],
            ['module' => 'users', 'action' => 'delete', 'description' => 'Delete users'],
            ['module' => 'users', 'action' => 'view_own_profile', 'description' => 'View own profile'],

            // Settings Permissions
            ['module' => 'settings', 'action' => 'view', 'description' => 'View settings'],
            ['module' => 'settings', 'action' => 'update', 'description' => 'Update settings'],
            ['module' => 'settings', 'action' => 'denied', 'description' => 'Access denied'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'module' => $permission['module'],
                'action' => $permission['action'],
                'description' => $permission['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        // Admin permissions - full access
        $adminPermissions = [
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
        ];

        // Kasir permissions - limited access
        $kasirPermissions = [
            'dashboard' => ['view_limited', 'sales_only'],
            'pos' => ['create', 'read', 'update_own'],
            'customers' => ['create', 'read', 'update_basic'],
            'inventory' => ['read', 'alert'],
            'products' => ['read', 'search'],
            'categories' => ['read'],
            'financial' => ['denied'],
            'fund_allocation' => ['denied'],
            'reports' => ['view_sales', 'export_limited'],
            'users' => ['view_own_profile'],
            'settings' => ['denied'],
        ];

        $this->insertRolePermissions('admin', $adminPermissions);
        $this->insertRolePermissions('kasir', $kasirPermissions);
    }

    private function insertRolePermissions(string $roleName, array $permissions): void
    {
        $role = DB::table('roles')->where('name', $roleName)->first();
        
        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $permission = DB::table('permissions')
                    ->where('module', $module)
                    ->where('action', $action)
                    ->first();
                
                if ($permission) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}