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
        Schema::table('fund_allocation_history', function (Blueprint $table) {
            // Menambahkan kolom business_id setelah kolom 'id'
            // dan menghubungkannya ke tabel 'businesses'
            $table->foreignId('business_id')
                  ->after('id')
                  ->constrained('businesses')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_allocation_history', function (Blueprint $table) {
            // Hapus foreign key dan kolomnya jika migrasi di-rollback
            $table->dropForeign(['business_id']);
            $table->dropColumn('business_id');
        });
    }
};