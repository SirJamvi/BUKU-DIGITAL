<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('difference_count')->default(0);
            $table->integer('total_system')->default(0);
            $table->integer('total_actual')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'created_by', 'created_at'], 'opname_sessions_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_sessions');
    }
};