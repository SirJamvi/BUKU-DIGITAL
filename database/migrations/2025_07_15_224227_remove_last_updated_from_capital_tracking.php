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
        Schema::table('capital_tracking', function (Blueprint $table) {
            // Hapus kolom last_updated
            $table->dropColumn('last_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_tracking', function (Blueprint $table) {
            // Kembalikan kolom last_updated
            $table->timestamp('last_updated')->after('status');
        });
    }
};