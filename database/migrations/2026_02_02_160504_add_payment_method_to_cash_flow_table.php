<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_flow', function (Blueprint $table) {
            // Menambahkan kolom payment_method setelah kolom amount
            // Kita gunakan tipe string agar fleksibel (bisa diisi 'cash', 'dana', 'transfer bank', dll)
            // Kita beri default 'cash' agar data lama tidak error
            $table->string('payment_method')->default('cash')->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_flow', function (Blueprint $table) {
            // Perintah untuk menghapus kolom jika migrasi dibatalkan (rollback)
            $table->dropColumn('payment_method');
        });
    }
};