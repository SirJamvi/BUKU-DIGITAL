<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; 

return new class extends Migration
{
    public function up(): void
    {
        // [DIPERBAIKI] Ubah slug yang terlanjur pakai spasi menjadi strip (standar URL/Database aman)
        DB::table('payment_methods')
            ->where('slug', 'transfer bank')
            ->update(['slug' => 'transfer-bank']);
    }

    public function down(): void
    {
        // Kembalikan ke spasi hanya jika di-rollback
        DB::table('payment_methods')
            ->where('slug', 'transfer-bank')
            ->update(['slug' => 'transfer bank']);
    }
};