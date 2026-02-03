<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ambil semua ID Bisnis
        $businesses = DB::table('businesses')->pluck('id');

        // 2. Definisi Metode Pembayaran
        $defaultMethods = [
            ['name' => 'Cash', 'slug' => 'cash', 'is_active' => true],
            ['name' => 'Dana', 'slug' => 'dana', 'is_active' => true],
            ['name' => 'Transfer Bank', 'slug' => 'transfer bank', 'is_active' => true],
        ];

        // 3. Looping insert data
        foreach ($businesses as $businessId) {
            foreach ($defaultMethods as $method) {
                // Gunakan insertOrIgnore atau updateOrInsert agar aman
                DB::table('payment_methods')->updateOrInsert(
                    [
                        'business_id' => $businessId, 
                        'slug'        => $method['slug']
                    ],
                    [
                        'name'       => $method['name'],
                        'is_active'  => $method['is_active'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Tidak perlu rollback data
    }
};