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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['sale', 'purchase', 'return']);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_method');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('transaction_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};