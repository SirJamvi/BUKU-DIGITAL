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
        Schema::table('expense_categories', function (Blueprint $table) {
            // Cek apakah kolom is_cogs belum ada, baru tambahkan
            if (!Schema::hasColumn('expense_categories', 'is_cogs')) {
                $table->boolean('is_cogs')->default(false)->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            // Hapus kolom hanya jika ada
            if (Schema::hasColumn('expense_categories', 'is_cogs')) {
                $table->dropColumn('is_cogs');
            }
        });
    }
};