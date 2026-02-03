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
    Schema::create('payment_methods', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('business_id')->index(); // Agar setiap bisnis punya metode sendiri (opsional)
        $table->string('name'); // Contoh: "Cash", "Dana", "Transfer Bank"
        $table->string('slug')->unique(); // Contoh: "cash", "dana", "transfer-bank" (untuk coding)
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    // Opsional: Seed data awal langsung di sini agar tabel tidak kosong
    DB::table('payment_methods')->insert([
        ['business_id' => 1, 'name' => 'Cash', 'slug' => 'cash', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'name' => 'Dana', 'slug' => 'dana', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 1, 'name' => 'Transfer Bank', 'slug' => 'transfer-bank', 'created_at' => now(), 'updated_at' => now()],
    ]);
}

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
