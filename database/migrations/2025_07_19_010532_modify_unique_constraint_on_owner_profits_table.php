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
        Schema::table('owner_profits', function (Blueprint $table) {
            // Hapus constraint unik yang lama
            $table->dropUnique('unique_owner_profit_period');

            // Buat constraint unik yang baru dan benar (termasuk business_id)
            $table->unique(['business_id', 'period_month', 'period_year'], 'unique_business_period_profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_profits', function (Blueprint $table) {
            // Hapus constraint unik yang baru
            $table->dropUnique('unique_business_period_profit');

            // Kembalikan constraint unik yang lama
            $table->unique(['period_month', 'period_year'], 'unique_owner_profit_period');
        });
    }
};