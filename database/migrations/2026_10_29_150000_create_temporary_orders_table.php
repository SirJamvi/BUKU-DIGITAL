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
        Schema::create('temporary_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable()->comment('Relasi ke tabel customers');
            $table->string('sender_phone', 50)->comment('Nomor WhatsApp pengirim dari Fonnte');
            $table->integer('jumlah_ball')->default(1)->comment('Jumlah ball/karung pesanan');
            $table->string('ukuran_produk', 20)->default('20kg')->comment('Ukuran es kristal: 20kg, 15kg, 10kg');
            $table->decimal('harga_asumsi', 15, 2)->default(0)->comment('Harga per ball hasil ekstraksi LLM');
            $table->decimal('total_harga', 15, 2)->default(0)->comment('Total harga: jumlah_ball * harga_asumsi');
            $table->enum('status', ['pending', 'validated', 'cancelled'])->default('pending')->comment('State status pesanan');
            $table->text('last_chat')->nullable()->comment('Isi chat terakhir dari pelanggan');
            $table->unsignedBigInteger('transaction_id')->nullable()->comment('ID transaksi POS saat sudah divalidasi');
            $table->timestamps();

            // Index untuk kecepatan query di shared hosting cPanel
            $table->index(['sender_phone', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_orders');
    }
};
