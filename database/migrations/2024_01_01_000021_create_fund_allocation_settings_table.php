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
        Schema::create('fund_allocation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_name', 100)->comment('Nama alokasi');
            $table->decimal('percentage', 5, 2)->comment('Persentase alokasi (0-100)');
            $table->boolean('is_active')->default(true)->comment('Status aktif');
            $table->enum('category', [
                'owner_salary',
                'reinvestment', 
                'emergency',
                'expansion',
                'custom'
            ])->comment('Kategori alokasi');
            $table->text('description')->nullable()->comment('Deskripsi alokasi');
            $table->integer('sort_order')->default(0)->comment('Urutan tampilan');
            $table->boolean('is_default')->default(false)->comment('Apakah pengaturan default');
            $table->decimal('min_percentage', 5, 2)->default(0)->comment('Minimum persentase');
            $table->decimal('max_percentage', 5, 2)->default(100)->comment('Maximum persentase');
            $table->boolean('is_mandatory')->default(false)->comment('Apakah alokasi wajib');
            $table->unsignedBigInteger('created_by')->comment('Dibuat oleh user ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('Diupdate oleh user ID');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('is_active');
            $table->index('category');
            $table->index('sort_order');
            $table->index('is_default');
            $table->index('created_by');
            
            // Unique constraint untuk nama alokasi
            // $table->unique('allocation_name', 'unique_allocation_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_allocation_settings');
    }
};