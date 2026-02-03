<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <--- INI WAJIB ADA AGAR TIDAK ERROR

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ==========================================
        // 1. UPDATE TABEL TRANSACTIONS
        // ==========================================
        
        // Ubah QRIS -> dana
        DB::table('transactions')
            ->where('payment_method', 'LIKE', '%qris%')
            ->update(['payment_method' => 'dana']);

        // Ubah Credit Card / Debit -> transfer bank
        DB::table('transactions')
            ->where(function($query) {
                $query->where('payment_method', 'LIKE', '%credit%')
                      ->orWhere('payment_method', 'LIKE', '%debit%')
                      ->orWhere('payment_method', 'LIKE', '%card%');
            })
            ->update(['payment_method' => 'transfer bank']);

        // Pastikan semua lowercase agar seragam
        DB::statement("UPDATE transactions SET payment_method = LOWER(payment_method)");


        // ==========================================
        // 2. UPDATE TABEL CASH FLOW
        // ==========================================
        
        // Cek dulu apakah kolom payment_method sudah ada di cash_flow
        if (Schema::hasColumn('cash_flow', 'payment_method')) {
            
            // Ubah QRIS -> dana
            DB::table('cash_flow')
                ->where('payment_method', 'LIKE', '%qris%')
                ->update(['payment_method' => 'dana']);

            // Ubah Credit Card / Debit -> transfer bank
            DB::table('cash_flow')
                ->where(function($query) {
                    $query->where('payment_method', 'LIKE', '%credit%')
                          ->orWhere('payment_method', 'LIKE', '%debit%')
                          ->orWhere('payment_method', 'LIKE', '%card%');
                })
                ->update(['payment_method' => 'transfer bank']);

            // Pastikan semua lowercase
            DB::statement("UPDATE cash_flow SET payment_method = LOWER(payment_method)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak perlu logika rollback untuk normalisasi data
    }
};