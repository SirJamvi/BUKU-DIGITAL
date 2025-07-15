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
        Schema::table('fund_allocation_settings', function (Blueprint $table) {
            // Hapus constraint unique yang lama (berdasarkan nama 'unique_allocation_name' di migrasi awal)
            $table->dropUnique('unique_allocation_name');

            // Tambahkan constraint unique yang baru dan benar (kombinasi business_id dan allocation_name)
            $table->unique(['business_id', 'allocation_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_allocation_settings', function (Blueprint $table) {
            // Kembalikan seperti semula jika migrasi di-rollback
            $table->dropUnique(['business_id', 'allocation_name']);
            $table->unique('allocation_name', 'unique_allocation_name');
        });
    }
};