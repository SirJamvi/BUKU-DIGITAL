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
        Schema::table('owner_profits', function (Blueprint $table) {
            // Cek apakah kolom monthly_income belum ada, baru tambahkan
            if (!Schema::hasColumn('owner_profits', 'monthly_income')) {
                $table->decimal('monthly_income', 15, 2)->default(0)->after('period_year');
            }
            
            // Cek apakah kolom gross_profit belum ada, baru tambahkan
            if (!Schema::hasColumn('owner_profits', 'gross_profit')) {
                $table->decimal('gross_profit', 15, 2)->default(0)->after('monthly_income');
            }
            
            // Cek apakah kolom monthly_expense belum ada, baru tambahkan
            if (!Schema::hasColumn('owner_profits', 'monthly_expense')) {
                $table->decimal('monthly_expense', 15, 2)->default(0)->after('gross_profit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_profits', function (Blueprint $table) {
            // Hapus kolom hanya jika ada
            $columnsToCheck = ['monthly_income', 'gross_profit', 'monthly_expense'];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('owner_profits', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};