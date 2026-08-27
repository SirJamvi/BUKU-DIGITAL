<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Menambahkan driver_id dan status pengiriman
            $table->unsignedBigInteger('driver_id')->nullable()->after('customer_id');
            $table->enum('delivery_status', ['pending', 'delivering', 'delivered'])->default('pending')->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['driver_id', 'delivery_status']);
        });
    }
};