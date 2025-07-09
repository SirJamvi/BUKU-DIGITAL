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
        Schema::create('owner_expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'salary',
                'personal',
                'business',
                'investment',
                'emergency',
                'expansion',
                'other'
            ])->comment('Jenis pengeluaran');
            $table->decimal('amount', 15, 2)->comment('Jumlah pengeluaran');
            $table->string('title', 200)->comment('Judul pengeluaran');
            $table->text('description')->nullable()->comment('Deskripsi pengeluaran');
            $table->date('date')->comment('Tanggal pengeluaran');
            $table->enum('category', [
                'operational',
                'personal',
                'investment',
                'emergency',
                'expansion',
                'tax',
                'other'
            ])->comment('Kategori pengeluaran');
            $table->enum('payment_method', [
                'cash',
                'transfer',
                'card',
                'other'
            ])->default('cash')->comment('Metode pembayaran');
            $table->string('reference_number', 100)->nullable()->comment('Nomor referensi');
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending')->comment('Status pengeluaran');
            $table->unsignedBigInteger('fund_allocation_history_id')->nullable()->comment('Relasi ke fund_allocation_history');
            $table->string('receipt_file', 500)->nullable()->comment('File bukti pembayaran');
            $table->boolean('is_recurring')->default(false)->comment('Apakah pengeluaran berulang');
            $table->enum('recurring_type', ['daily', 'weekly', 'monthly', 'yearly'])->nullable()->comment('Jenis pengulangan');
            $table->integer('recurring_interval')->default(1)->comment('Interval pengulangan');
            $table->date('recurring_end_date')->nullable()->comment('Tanggal berakhir pengulangan');
            $table->boolean('is_tax_deductible')->default(false)->comment('Apakah dapat dikurangkan pajak');
            $table->text('notes')->nullable()->comment('Catatan tambahan');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('Disetujui oleh user ID');
            $table->timestamp('approved_at')->nullable()->comment('Waktu persetujuan');
            $table->unsignedBigInteger('created_by')->comment('Dibuat oleh user ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Diupdate oleh user ID');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('fund_allocation_history_id')->references('id')->on('fund_allocation_history')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('type');
            $table->index('category');
            $table->index('date');
            $table->index('status');
            $table->index('payment_method');
            $table->index('is_recurring');
            $table->index('is_tax_deductible');
            $table->index('approved_at');
            $table->index('fund_allocation_history_id');
            $table->index('created_by');
            $table->index('approved_by');
            
            // Composite indexes untuk query yang sering digunakan
            $table->index(['date', 'type'], 'idx_date_type');
            $table->index(['status', 'date'], 'idx_status_date');
            $table->index(['type', 'category'], 'idx_type_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_expenses');
    }
};