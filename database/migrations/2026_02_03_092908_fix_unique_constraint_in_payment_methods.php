<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // 1. Hapus aturan unik yang lama (Global Unique)
            // Kita gunakan try-catch atau pengecekan untuk menghindari error jika index tidak ditemukan
            try {
                $table->dropUnique('payment_methods_slug_unique');
            } catch (\Exception $e) {
                // Lanjut jika index sudah tidak ada
            }

            // 2. Buat aturan unik baru (Composite Unique)
            // Unik berdasarkan kombinasi (business_id + slug)
            // Artinya: Bisnis A boleh punya 'cash', Bisnis B juga boleh punya 'cash'
            $table->unique(['business_id', 'slug'], 'payment_methods_business_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique('payment_methods_business_slug_unique');
            $table->unique('slug', 'payment_methods_slug_unique');
        });
    }
};