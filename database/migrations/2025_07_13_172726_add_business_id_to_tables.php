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
        // Tabel yang akan dimodifikasi
        $tables = [
            'users',
            'products',
            'product_categories',
            'inventory',
            'transactions',
            'customers',
            'suppliers',
            'cash_flow',
            'expense_categories',
            'fund_allocation_settings',
            'roles' // Roles juga bisa spesifik per bisnis
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // Menambahkan kolom business_id setelah kolom 'id'
                // Dibuat nullable agar tidak error pada data yang sudah ada
                $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'products',
            'product_categories',
            'inventory',
            'transactions',
            'customers',
            'suppliers',
            'cash_flow',
            'expense_categories',
            'fund_allocation_settings',
            'roles'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // Hapus foreign key constraint terlebih dahulu
                $table->dropForeign(['business_id']);
                // Hapus kolomnya
                $table->dropColumn('business_id');
            });
        }
    }
};