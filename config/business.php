 
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Business-specific Settings
    |--------------------------------------------------------------------------
    |
    | Simpan semua pengaturan yang berhubungan dengan logika bisnis di sini.
    |
    */

    'currency' => 'IDR',
    'currency_symbol' => 'Rp',

    // Persentase pajak default, bisa di-override di level lain
    'default_tax_rate' => env('DEFAULT_TAX_RATE', 11), // PPN 11%

    // Pengaturan untuk alokasi dana default
    'fund_allocation' => [
        'owner_salary' => 40,
        'reinvestment' => 30,
        'emergency' => 20,
        'expansion' => 10,
    ],
];