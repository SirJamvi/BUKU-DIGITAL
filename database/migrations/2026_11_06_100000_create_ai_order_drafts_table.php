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
        Schema::create('ai_order_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('sender_phone', 50)->unique()->index();
            $table->json('data')->nullable();
            $table->string('awaiting', 50)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_order_drafts');
    }
};

