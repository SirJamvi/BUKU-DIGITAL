<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daftar Izin (Permissions) Aplikasi
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'dashboard', 'users', 'products', 'categories', 'inventory',
        'customers', 'pos', 'transactions', 'financial',
        'fund_allocation', 'reports', 'settings',
    ],

    'actions' => [
        'view_all', 'view_limited', 'view_own', 'create', 'update',
        'update_own', 'delete', 'analyze', 'export', 'opname', 'override',
    ],
];