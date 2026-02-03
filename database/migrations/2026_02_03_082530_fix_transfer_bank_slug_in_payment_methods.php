<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Wajib import DB Facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah slug dari 'transfer-bank' (strip) menjadi 'transfer bank' (spasi)
        // Agar COCOK dengan data transaksi lama yang sudah dinormalisasi
        DB::table('payment_methods')
            ->where('slug', 'transfer-bank')
            ->update(['slug' => 'transfer bank']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke 'transfer-bank' jika migrate:rollback dijalankan
        DB::table('payment_methods')
            ->where('slug', 'transfer bank')
            ->update(['slug' => 'transfer-bank']);
    }
};