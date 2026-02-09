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
        // 1. Memperbarui kolom ROLE agar bisa menerima 'driver'
        // Kita gunakan DB::statement karena mengubah ENUM via Schema Builder sering bermasalah
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'kasir', 'driver') NOT NULL DEFAULT 'kasir'");

        // 2. Menambahkan kolom SLUG (Untuk mengatasi error 'slug' column not found)
        // Kita cek dulu apakah kolomnya belum ada
        if (!Schema::hasColumn('users', 'slug')) {
            Schema::table('users', function (Blueprint $table) {
                // Tambahkan slug, set nullable agar data lama aman
                $table->string('slug')->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback kolom slug
        if (Schema::hasColumn('users', 'slug')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }

        // Rollback role (Hati-hati: jika ada user 'driver', ini akan error jika data tidak dibersihkan dulu)
        // DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir'");
    }
};