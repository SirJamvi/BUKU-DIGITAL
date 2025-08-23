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
        Schema::table('fund_allocation_history', function (Blueprint $table) {
            // Mengubah tipe kolom menjadi string (varchar) agar lebih fleksibel
            $table->string('allocation_category')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_allocation_history', function (Blueprint $table) {
            // Kembalikan ke enum jika diperlukan (sesuaikan dengan enum Anda sebelumnya)
            // Anda mungkin perlu menyesuaikan daftar enum ini
            $table->enum('allocation_category', ['Operasional', 'Investasi', 'Tabungan', 'Dividen'])->nullable()->change();
        });
    }
};