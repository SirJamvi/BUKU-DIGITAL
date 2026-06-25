<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
            $table->foreignId('from_product_id')->constrained('products')->onDelete('cascade'); // Karung 20kg
            $table->foreignId('to_product_id')->constrained('products')->onDelete('cascade');   // Eceran 5rb
            $table->integer('quantity_to_break')->default(1);
            $table->integer('yield_amount'); // Menghasilkan 4 atau 5 eceran
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_conversions');
    }
};