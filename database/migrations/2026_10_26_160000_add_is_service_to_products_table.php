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
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('is_active');
        });

        // Tandai produk jasa (ongkir / ongkos kirim / unit 'Jalan') sebagai is_service = true
        DB::table('products')
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%ongkir%'])
                      ->orWhereRaw('LOWER(name) LIKE ?', ['%ongkos kirim%'])
                      ->orWhereRaw('LOWER(unit) = ?', ['jalan']);
            })
            ->update(['is_service' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
