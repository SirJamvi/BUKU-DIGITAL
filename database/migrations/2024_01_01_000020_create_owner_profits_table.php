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
        Schema::create('owner_profits', function (Blueprint $table) {
            $table->id();
            $table->integer('period_month')->comment('Bulan periode (1-12)');
            $table->integer('period_year')->comment('Tahun periode');
            $table->decimal('net_profit', 15, 2)->default(0)->comment('Keuntungan bersih');
            $table->decimal('owner_salary', 15, 2)->default(0)->comment('Gaji owner');
            $table->decimal('withdrawal_amount', 15, 2)->default(0)->comment('Jumlah penarikan');
            $table->decimal('reinvestment', 15, 2)->default(0)->comment('Reinvestasi bisnis');
            $table->decimal('allocated_funds', 15, 2)->default(0)->comment('Total dana yang dialokasikan');
            $table->json('allocation_settings')->nullable()->comment('Pengaturan alokasi dalam format JSON');
            $table->boolean('auto_allocated')->default(false)->comment('Apakah alokasi otomatis');
            $table->boolean('manual_override')->default(false)->comment('Apakah ada override manual');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->enum('status', ['pending', 'allocated', 'completed'])->default('pending')->comment('Status alokasi');
            $table->timestamp('allocated_at')->nullable()->comment('Waktu alokasi');
            $table->timestamps();
            
            // Indexes
            $table->index(['period_month', 'period_year']);
            $table->index('status');
            $table->index('allocated_at');
            
            // Unique constraint untuk mencegah duplikasi periode
            $table->unique(['period_month', 'period_year'], 'unique_owner_profit_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_profits');
    }
};