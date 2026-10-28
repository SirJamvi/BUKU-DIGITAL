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
        // 1. Tambah kolom bukti pada tabel cash_flow (untuk bukti nota pengeluaran)
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (!Schema::hasColumn('cash_flow', 'receipt_image')) {
                    $table->string('receipt_image', 500)->nullable()->after('description');
                }
                if (!Schema::hasColumn('cash_flow', 'proof_verified_at')) {
                    $table->timestamp('proof_verified_at')->nullable()->after('receipt_image');
                }
                if (!Schema::hasColumn('cash_flow', 'proof_verified_by')) {
                    $table->foreignId('proof_verified_by')->nullable()->after('proof_verified_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        // 2. Tambah kolom bukti pada tabel transactions (untuk bukti transfer WA pelunasan non-tunai)
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'payment_proof')) {
                    $table->string('payment_proof', 500)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('transactions', 'proof_verified_at')) {
                    $table->timestamp('proof_verified_at')->nullable()->after('payment_proof');
                }
                if (!Schema::hasColumn('transactions', 'proof_verified_by')) {
                    $table->foreignId('proof_verified_by')->nullable()->after('proof_verified_at')->constrained('users')->nullOnDelete();
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
                    $table->dropForeign(['proof_verified_by']);
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
                    $table->dropForeign(['proof_verified_by']);
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
