<?php
/**
 * DOKUMEN PRODUKSI #03: Migrasi Penambahan Kolom is_cogs pada Tabel cash_flow
 * 
 * PRASYARAT:
 * - Dijalankan sebelum script backfill 04_BACKFILL_CASH_FLOW_IS_COGS.sql.
 * - Setelah kolom ini dibuat dan di-backfill, baru config accounting.use_cashflow_is_cogs dapat diaktifkan menjadi true.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cash_flow', 'is_cogs')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                $table->boolean('is_cogs')->default(false)->after('category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_flow', 'is_cogs')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                $table->dropColumn('is_cogs');
            });
        }
    }
};
