<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('temporary_orders', function (Blueprint $table) {
                $table->decimal('jumlah_ball', 8, 2)->default(1.00)->change();
            });
        } else {
            DB::statement("ALTER TABLE temporary_orders MODIFY COLUMN jumlah_ball DECIMAL(8, 2) NOT NULL DEFAULT 1.00");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('temporary_orders', function (Blueprint $table) {
                $table->integer('jumlah_ball')->default(1)->change();
            });
        } else {
            DB::statement("ALTER TABLE temporary_orders MODIFY COLUMN jumlah_ball INT NOT NULL DEFAULT 1");
        }
    }
};

