<?php
/**
 * DOKUMEN PRODUKSI #06: Migrasi Penambahan Kolom category_type pada expense_categories
 * 
 * PRASYARAT:
 * - Tidak menghapus atau mengubah kolom is_cogs yang sudah ada.
 * - Memfasilitasi klasifikasi akuntansi terstruktur: HPP, Operasional, Piutang Karyawan, Non-usaha.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('expense_categories', 'category_type')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->enum('category_type', ['HPP', 'Operasional', 'Piutang Karyawan', 'Non-usaha'])
                    ->default('Operasional')
                    ->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_categories', 'category_type')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->dropColumn('category_type');
            });
        }
    }
};
