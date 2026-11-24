<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mensinkronkan order_status dan delivery_status menjadi 'cancelled'
     * untuk seluruh transaksi yang sudah di-void atau dibatalkan sebelumnya.
     */
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            // Perluas ENUM delivery_status di MySQL agar mencakup 'cancelled'
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE transactions MODIFY COLUMN delivery_status ENUM('pending','delivering','delivered','cancelled') NOT NULL DEFAULT 'pending'");
            }

            DB::table('transactions')
                ->where(function ($q) {
                    $q->where('status', 'cancelled')
                      ->orWhereNotNull('voided_at');
                })
                ->where(function ($q) {
                    $q->whereIn('order_status', ['delivering', 'pending'])
                      ->orWhereIn('delivery_status', ['delivering', 'pending']);
                })
                ->update([
                    'order_status'    => 'cancelled',
                    'delivery_status' => 'cancelled',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN delivery_status ENUM('pending','delivering','delivered') NOT NULL DEFAULT 'pending'");
        }
    }
};
