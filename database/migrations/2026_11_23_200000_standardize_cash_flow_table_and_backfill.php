<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menstandarisasi skema tabel cash_flow dengan flow_type, reference_type, serta backfill data historis.
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (!Schema::hasColumn('cash_flow', 'flow_type')) {
                    $table->string('flow_type', 50)->default('expense')->after('type')->index();
                }
                if (!Schema::hasColumn('cash_flow', 'reference_type')) {
                    $table->string('reference_type', 50)->nullable()->after('reference_id')->index();
                }
            });

            // 1. Backfill pembersihan nilai NULL pada funding_source, affects_drawer, dan payment_method
            // a. Jika funding_source adalah owner/external, pastikan affects_drawer = 0
            DB::table('cash_flow')
                ->whereIn('funding_source', ['owner', 'external'])
                ->update(['affects_drawer' => 0]);

            // b. Bersihkan payment_method NULL menjadi 'cash'
            DB::table('cash_flow')
                ->whereNull('payment_method')
                ->update(['payment_method' => 'cash']);

            // c. Bersihkan funding_source NULL
            DB::table('cash_flow')
                ->whereNull('funding_source')
                ->where('affects_drawer', 1)
                ->update(['funding_source' => 'drawer']);

            DB::table('cash_flow')
                ->whereNull('funding_source')
                ->where('affects_drawer', 0)
                ->update(['funding_source' => 'owner']);

            // d. Bersihkan sisa affects_drawer NULL
            DB::table('cash_flow')
                ->whereNull('affects_drawer')
                ->update(['affects_drawer' => 1]);

            // 2. Backfill flow_type dan reference_type secara terstruktur dari data historis
            // a. Gaji Karyawan dari sistem absensi
            DB::table('cash_flow')
                ->where('reference_id', 'LIKE', 'ATT-%')
                ->update([
                    'flow_type'      => 'salary_external',
                    'reference_type' => 'attendance_salary',
                    'funding_source' => 'external',
                    'affects_drawer' => 0,
                ]);

            // b. Pelunasan Kasbon & Setoran Driver
            DB::table('cash_flow')
                ->where('type', 'income')
                ->where('description', 'LIKE', '%Pelunasan%')
                ->update([
                    'flow_type'      => 'debt_settlement',
                    'reference_type' => 'transaction',
                ]);

            // c. Penjualan Langsung Lunas POS (Income non-pelunasan)
            DB::table('cash_flow')
                ->where('type', 'income')
                ->where('flow_type', '!=', 'debt_settlement')
                ->update([
                    'flow_type'      => 'sale_direct',
                    'reference_type' => 'transaction',
                ]);

            // d. Pembelian Stok Es / Refill HPP
            DB::table('cash_flow')
                ->where('type', 'expense')
                ->where('is_cogs', 1)
                ->where('flow_type', '!=', 'salary_external')
                ->update([
                    'flow_type'      => 'stock_purchase',
                    'reference_type' => 'manual',
                ]);

            // e. Beban operasional murni lainnya
            DB::table('cash_flow')
                ->where('type', 'expense')
                ->where('flow_type', 'expense')
                ->whereNull('reference_type')
                ->update([
                    'reference_type' => 'manual',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (Schema::hasColumn('cash_flow', 'reference_type')) {
                    $table->dropColumn('reference_type');
                }
                if (Schema::hasColumn('cash_flow', 'flow_type')) {
                    $table->dropColumn('flow_type');
                }
            });
        }
    }
};
