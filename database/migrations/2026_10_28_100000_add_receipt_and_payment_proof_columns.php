<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 0. Nonaktifkan sementara strict mode sql_mode untuk session migrasi ini
        // Ini mencegah error MySQL 1292: Incorrect date value: '0000-00-00'
        try {
            DB::statement("SET SESSION sql_mode = ''");
        } catch (\Throwable $e) {}

        // 1. Bersihkan dan perbaiki data tanggal legacy '0000-00-00' pada cash_flow agar ALTER TABLE tidak crash
        if (Schema::hasTable('cash_flow')) {
            try {
                DB::statement("UPDATE `cash_flow` SET `date` = '2024-01-01' WHERE CAST(`date` AS CHAR) LIKE '0000-00-00%' OR `date` = '0000-00-00' OR `date` IS NULL");
            } catch (\Throwable $e) {}

            try {
                DB::statement("UPDATE `cash_flow` SET `created_at` = NOW() WHERE CAST(`created_at` AS CHAR) LIKE '0000-00-00%'");
                DB::statement("UPDATE `cash_flow` SET `updated_at` = NOW() WHERE CAST(`updated_at` AS CHAR) LIKE '0000-00-00%'");
            } catch (\Throwable $e) {}
        }

        // 2. Bersihkan juga data '0000-00-00' pada transactions
        if (Schema::hasTable('transactions')) {
            try {
                DB::statement("UPDATE `transactions` SET `transaction_date` = NOW() WHERE CAST(`transaction_date` AS CHAR) LIKE '0000-00-00%'");
            } catch (\Throwable $e) {}

            try {
                DB::statement("UPDATE `transactions` SET `created_at` = NOW() WHERE CAST(`created_at` AS CHAR) LIKE '0000-00-00%'");
                DB::statement("UPDATE `transactions` SET `updated_at` = NOW() WHERE CAST(`updated_at` AS CHAR) LIKE '0000-00-00%'");
            } catch (\Throwable $e) {}
        }

        // 3. Tambah kolom bukti pada tabel cash_flow (untuk bukti nota pengeluaran)
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (!Schema::hasColumn('cash_flow', 'receipt_image')) {
                    $table->string('receipt_image', 500)->nullable()->after('description');
                }
                if (!Schema::hasColumn('cash_flow', 'proof_verified_at')) {
                    $table->timestamp('proof_verified_at')->nullable()->after('receipt_image');
                }
                if (!Schema::hasColumn('cash_flow', 'proof_verified_by')) {
                    $table->unsignedBigInteger('proof_verified_by')->nullable()->after('proof_verified_at')->index();
                }
            });
        }

        // 4. Tambah kolom bukti pada tabel transactions (untuk bukti transfer WA pelunasan non-tunai)
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'payment_proof')) {
                    $table->string('payment_proof', 500)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('transactions', 'proof_verified_at')) {
                    $table->timestamp('proof_verified_at')->nullable()->after('payment_proof');
                }
                if (!Schema::hasColumn('transactions', 'proof_verified_by')) {
                    $table->unsignedBigInteger('proof_verified_by')->nullable()->after('proof_verified_at')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (Schema::hasColumn('cash_flow', 'proof_verified_by')) {
                    $table->dropColumn('proof_verified_by');
                }
                if (Schema::hasColumn('cash_flow', 'proof_verified_at')) {
                    $table->dropColumn('proof_verified_at');
                }
                if (Schema::hasColumn('cash_flow', 'receipt_image')) {
                    $table->dropColumn('receipt_image');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'proof_verified_by')) {
                    $table->dropColumn('proof_verified_by');
                }
                if (Schema::hasColumn('transactions', 'proof_verified_at')) {
                    $table->dropColumn('proof_verified_at');
                }
                if (Schema::hasColumn('transactions', 'payment_proof')) {
                    $table->dropColumn('payment_proof');
                }
            });
        }
    }
};
