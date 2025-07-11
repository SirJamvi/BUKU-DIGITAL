<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('SESSION_DRIVER', 'database'), // Menggunakan database agar sesi lebih persisten

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    */
    // Sesi akan berakhir setelah 120 menit tidak ada aktivitas
    'lifetime' => (int) env('SESSION_LIFETIME', 120), 

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    */
    'encrypt' => env('SESSION_ENCRYPT', true), // Enkripsi sesi untuk keamanan tambahan

    // ... (bagian lain biarkan default) ...

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    */
    // Tabel ini akan digunakan jika driver adalah 'database'
    'table' => env('SESSION_TABLE', 'sessions'), 

    // ... (bagian lain biarkan default) ...

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    */
    // 'lax' adalah pengaturan yang seimbang antara keamanan dan kegunaan
    'same_site' => env('SESSION_SAME_SITE', 'lax'), 

];