<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Strict COGS Enforcement
    |--------------------------------------------------------------------------
    |
    | false (Paket A): Baris dengan cost_price NULL tetap fallback ke master product,
    | namun dihitung ke missing_cogs_count dan missing_cogs_revenue.
    | true (Paket B): Baris dengan cost_price NULL tidak lagi membaca master product,
    | melainkan langsung dihitung 0.
    |
    */
    'strict_cogs' => env('ACCOUNTING_STRICT_COGS', false),

    /*
    |--------------------------------------------------------------------------
    | Use Snapshot is_cogs from cash_flow table
    |--------------------------------------------------------------------------
    |
    | false: Membaca status is_cogs via relasi join expense_categories.
    | true: Membaca status is_cogs langsung dari kolom snapshot cash_flow.is_cogs.
    |
    */
    'use_cashflow_is_cogs' => env('ACCOUNTING_USE_CASHFLOW_IS_COGS', false),
];
