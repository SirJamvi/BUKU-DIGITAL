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
        // 1. Indeks Performa untuk tabel `transactions`
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                // Indeks gabungan untuk query statistik harian kasir & dashboard kasir
                $table->index(['business_id', 'created_by', 'type', 'transaction_date'], 'idx_trx_kasir_summary');
                
                // Indeks untuk listing riwayat transaksi kasir
                $table->index(['created_by', 'transaction_date'], 'idx_trx_kasir_date');
                
                // Indeks filter transaksi per cabang dan rentang tanggal
                $table->index(['branch_id', 'transaction_date'], 'idx_trx_branch_date');
                
                // Indeks status pembayaran & piutang kasbon
                $table->index(['business_id', 'type', 'payment_status', 'transaction_date'], 'idx_trx_status_date');
                
                // Indeks pengiriman driver & delivery trip
                $table->index(['driver_id', 'delivery_trip_code'], 'idx_trx_driver_trip');
            });
        }

        // 2. Indeks Performa untuk tabel `cash_flow`
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                // Indeks arus kas per kasir harian
                $table->index(['business_id', 'created_by', 'date'], 'idx_cashflow_kasir_date');
                
                // Indeks arus kas per cabang harian
                $table->index(['branch_id', 'date'], 'idx_cashflow_branch_date');
                
                // Indeks referensi transaksi (pencocokan pelunasan kasbon)
                $table->index(['reference_id'], 'idx_cashflow_ref');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('idx_trx_kasir_summary');
                $table->dropIndex('idx_trx_kasir_date');
                $table->dropIndex('idx_trx_branch_date');
                $table->dropIndex('idx_trx_status_date');
                $table->dropIndex('idx_trx_driver_trip');
            });
        }

        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                $table->dropIndex('idx_cashflow_kasir_date');
                $table->dropIndex('idx_cashflow_branch_date');
                $table->dropIndex('idx_cashflow_ref');
            });
        }
    }
};
