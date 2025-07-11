<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Definisi Peran (Roles) dan Izin Aplikasi
    |--------------------------------------------------------------------------
    | Disesuaikan dengan Permission Matrix dari SOP.
    */

    'admin' => [
        'display_name' => 'Admin (Owner)',
        'description' => 'Memiliki akses penuh ke semua fitur strategis dan operasional sistem.',
        'permissions' => [
            // Dashboard & Analytics
            'dashboard.view_all', 'dashboard.analytics', 'dashboard.kpi',

            // POS & Sales
            'pos.create', 'pos.read', 'pos.update', 'pos.delete',

            // Customers
            'customers.create', 'customers.read', 'customers.update', 'customers.delete', 'customers.analyze',

            // Inventory & Products
            'inventory.create', 'inventory.read', 'inventory.update', 'inventory.delete', 'inventory.opname',
            'products.create', 'products.read', 'products.update', 'products.delete', 'products.bulk_import',
            'categories.create', 'categories.read', 'categories.update', 'categories.delete',

            // Financial
            'financial.view_all', 'financial.analyze', 'financial.export',

            // Fund Allocation
            'fund_allocation.view', 'fund_allocation.create', 'fund_allocation.update', 'fund_allocation.override',
            
            // Reports
            'reports.view_all', 'reports.export', 'reports.schedule',

            // User Management
            'users.create', 'users.read', 'users.update', 'users.delete',

            // Settings
            'settings.view', 'settings.update',
        ]
    ],

    'kasir' => [
        'display_name' => 'Kasir',
        'description' => 'Bertanggung jawab untuk operasional penjualan di Point of Sale.',
        'permissions' => [
            // Dashboard
            'dashboard.view_limited', 'dashboard.sales_only',

            // POS
            'pos.create', 'pos.read', 'pos.update_own',

            // Customers
            'customers.create', 'customers.read', 'customers.update_basic',

            // Inventory
            'inventory.read', 'inventory.alert',

            // Products & Categories
            'products.read', 'products.search',
            'categories.read',

            // Financial & Fund Allocation (Denied)
            'financial.denied',
            'fund_allocation.denied',

            // Reports
            'reports.view_sales', 'reports.export_limited',

            // User & Settings
            'users.view_own_profile',
            'settings.denied',
        ]
    ],
];