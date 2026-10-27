<?php
/**
 * DOKUMEN PRODUKSI #01: Migrasi Penambahan Kolom is_service pada Tabel products
 * 
 * PRASYARAT:
 * - Tidak ada prasyarat khusus (Aman dijalankan kapan saja).
 * - Menambahkan kolom boolean is_service dengan default false.
 * - Otomatis menandai produk jasa (ongkir / ongkos kirim / unit 'Jalan') sebagai true.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'is_service')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_service')->default(false)->after('is_active');
            });
        }

        // Tandai produk jasa (ongkir / ongkos kirim / unit 'Jalan') sebagai is_service = true
        DB::table('products')
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%ongkir%'])
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%ongkos kirim%'])
                      ->orWhereRaw('LOWER(unit) = ?', ['jalan']);
            })
            ->update(['is_service' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'is_service')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('is_service');
            });
        }
    }
};
