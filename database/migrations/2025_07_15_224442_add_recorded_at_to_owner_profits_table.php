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
            // Tambahkan kolom recorded_at
            $table->timestamp('recorded_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owner_profits', function (Blueprint $table) {
            // Hapus kolom recorded_at
            $table->dropColumn('recorded_at');
        });
    }
};