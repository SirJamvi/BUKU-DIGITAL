<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan opsi 'void_return' ke ENUM kolom 'type' di tabel stock_movements.
     */
    public function up(): void
    {
        if (Schema::hasTable('stock_movements') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('in','out','adjustment','waste','sale','transfer_out','transfer_in','void_return') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_movements') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('in','out','adjustment','waste','sale','transfer_out','transfer_in') NOT NULL");
        }
    }
};
