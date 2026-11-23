<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah pengeluaran gaji karyawan menjadi funding_source = 'external' dan affects_drawer = 0
     * agar data gaji tetap tercatat di sistem sebagai beban operasional tetapi TIDAK MEMOTONG uang fisik di laci kasir.
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_flow')) {
            // 1. Update data pengeluaran gaji dari sinkronisasi absensi (reference_id diawali ATT-)
            DB::table('cash_flow')
                ->where('type', 'expense')
                ->where('reference_id', 'LIKE', 'ATT-%')
                ->update([
                    'funding_source' => 'external',
                    'affects_drawer' => 0,
                ]);

            // 2. Update data pengeluaran berdasarkan kategori Gaji / Salary
            if (Schema::hasTable('expense_categories')) {
                $salaryCategoryIds = DB::table('expense_categories')
                    ->where(function ($q) {
                        $q->where('name', 'LIKE', '%Gaji%')
                          ->orWhere('name', 'LIKE', '%Salary%')
                          ->orWhere('name', 'LIKE', '%Payroll%');
                    })
                    ->pluck('id')
                    ->toArray();

                if (!empty($salaryCategoryIds)) {
                    DB::table('cash_flow')
                        ->where('type', 'expense')
                        ->whereIn('category_id', $salaryCategoryIds)
                        ->update([
                            'funding_source' => 'external',
                            'affects_drawer' => 0,
                        ]);
                }
            }

            // 3. Update pengeluaran yang deskripsinya secara eksplisit menyebut Gaji
            DB::table('cash_flow')
                ->where('type', 'expense')
                ->where(function ($q) {
                    $q->where('description', 'LIKE', '%Gaji%')
                      ->orWhere('description', 'LIKE', '%Salary%')
                      ->orWhere('description', 'LIKE', '%Payroll%');
                })
                ->update([
                    'funding_source' => 'external',
                    'affects_drawer' => 0,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data update tidak perlu di-rollback secara destruktif
    }
};
