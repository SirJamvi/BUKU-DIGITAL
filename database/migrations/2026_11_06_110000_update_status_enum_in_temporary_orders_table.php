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
        // Ubah kolom status agar mendukung 'processed' dan 'expired' sesuai state machine
        DB::statement("ALTER TABLE temporary_orders MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE temporary_orders MODIFY COLUMN status ENUM('pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
