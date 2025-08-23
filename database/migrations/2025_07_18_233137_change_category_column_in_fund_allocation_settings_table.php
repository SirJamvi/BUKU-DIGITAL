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
        Schema::table('fund_allocation_settings', function (Blueprint $table) {
            // Mengubah tipe kolom menjadi string (varchar) agar lebih fleksibel
            $table->string('category')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fund_allocation_settings', function (Blueprint $table) {
            // Kembalikan ke enum jika diperlukan (sesuaikan dengan enum Anda sebelumnya)
            $table->enum('category', ['salary', 'investment', 'emergency', 'dividend', 'others'])->nullable()->change();
        });
    }
};