<?php
/**
 * DOKUMEN PRODUKSI #05: Migrasi Pengetatan NOT NULL pada transaction_details.cost_price
 * 
 * PRASYARAT MUTLAK:
 * 1. DILARANG DIJALANKAN sebelum user menulis "backfill selesai" dan backup database selesai dilakukan.
 * 2. Seluruh baris cost_price bernilai NULL pada transaction_details di database produksi telah selesai dibackfill.
 * 3. Jika masih ada NULL saat migrasi ini dijalankan, alter table akan gagal (Integrity Constraint Violation).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(0.00)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable(true)->change();
        });
    }
};
