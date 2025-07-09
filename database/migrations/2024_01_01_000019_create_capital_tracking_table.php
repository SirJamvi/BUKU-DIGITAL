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
        Schema::create('capital_tracking', function (Blueprint $table) {
            $table->id();
            $table->decimal('initial_capital', 15, 2);
            $table->decimal('additional_capital', 15, 2)->default(0);
            $table->decimal('total_returned', 15, 2)->default(0);
            $table->enum('status', ['active', 'returned', 'partial'])->default('active');
            $table->timestamp('last_updated');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capital_tracking');
    }
};