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
        Schema::create('fund_allocation_history', function (Blueprint $table) {
            $table->id();
            $table->integer('period_month')->comment('Bulan periode (1-12)');
            $table->integer('period_year')->comment('Tahun periode');
            $table->decimal('net_profit', 15, 2)->comment('Keuntungan bersih periode');
            $table->decimal('allocated_amount', 15, 2)->comment('Jumlah yang dialokasikan');
            $table->enum('allocation_category', [
                'owner_salary',
                'reinvestment',
                'emergency',
                'expansion',
                'custom'
            ])->comment('Kategori alokasi');
            $table->decimal('allocation_percentage', 5, 2)->comment('Persentase alokasi');
            $table->string('allocation_name', 100)->comment('Nama alokasi');
            $table->boolean('is_manual')->default(false)->comment('Apakah alokasi manual');
            $table->text('notes')->nullable()->comment('Catatan alokasi');
            $table->enum('status', ['pending', 'allocated', 'used', 'cancelled'])->default('allocated')->comment('Status alokasi');
            $table->unsignedBigInteger('owner_profit_id')->nullable()->comment('Relasi ke owner_profits');
            $table->unsignedBigInteger('fund_allocation_setting_id')->nullable()->comment('Relasi ke fund_allocation_settings');
            $table->unsignedBigInteger('created_by')->comment('Dibuat oleh user ID');
            $table->timestamp('allocated_at')->nullable()->comment('Waktu alokasi');
            $table->timestamp('used_at')->nullable()->comment('Waktu penggunaan');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('owner_profit_id')->references('id')->on('owner_profits')->onDelete('cascade');
            $table->foreign('fund_allocation_setting_id')->references('id')->on('fund_allocation_settings')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['period_month', 'period_year']);
            $table->index('allocation_category');
            $table->index('status');
            $table->index('is_manual');
            $table->index('allocated_at');
            $table->index('used_at');
            $table->index('owner_profit_id');
            $table->index('fund_allocation_setting_id');
            $table->index('created_by');
            
            // Composite index untuk query yang sering digunakan
            $table->index(['period_month', 'period_year', 'allocation_category'], 'idx_period_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_allocation_history');
    }
};