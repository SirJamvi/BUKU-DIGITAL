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
        Schema::table('temporary_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('temporary_orders', 'is_new_customer')) {
                $table->boolean('is_new_customer')->default(false)->after('status')->comment('True jika dibuat bersamaan pendaftaran pelanggan baru');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_orders', function (Blueprint $table) {
            if (Schema::hasColumn('temporary_orders', 'is_new_customer')) {
                $table->dropColumn('is_new_customer');
            }
        });
    }
};
