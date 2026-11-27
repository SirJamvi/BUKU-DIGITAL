<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom audit pembalikan (reversal / void) pada tabel customer_deposit_logs.
     * Memastikan prinsip Zero Hard-Delete dan ketertelusuran FMCG penuh.
     */
    public function up(): void
    {
        if (!Schema::hasTable('customer_deposit_logs')) {
            return;
        }

        Schema::table('customer_deposit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_deposit_logs', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('customer_deposit_logs', 'reversed_by')) {
                $table->foreignId('reversed_by')
                    ->nullable()
                    ->after('reversed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('customer_deposit_logs', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            }
            if (!Schema::hasColumn('customer_deposit_logs', 'reversed_by_log_id')) {
                $table->unsignedBigInteger('reversed_by_log_id')->nullable()->after('reversal_reason');
                $table->index('reversed_by_log_id', 'cdl_reversed_by_log_idx');
            }
            if (!Schema::hasColumn('customer_deposit_logs', 'reverses_log_id')) {
                $table->unsignedBigInteger('reverses_log_id')->nullable()->after('reversed_by_log_id');
                $table->index('reverses_log_id', 'cdl_reverses_log_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('customer_deposit_logs')) {
            return;
        }

        Schema::table('customer_deposit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('customer_deposit_logs', 'reverses_log_id')) {
                $table->dropIndex('cdl_reverses_log_idx');
                $table->dropColumn('reverses_log_id');
            }
            if (Schema::hasColumn('customer_deposit_logs', 'reversed_by_log_id')) {
                $table->dropIndex('cdl_reversed_by_log_idx');
                $table->dropColumn('reversed_by_log_id');
            }
            if (Schema::hasColumn('customer_deposit_logs', 'reversal_reason')) {
                $table->dropColumn('reversal_reason');
            }
            if (Schema::hasColumn('customer_deposit_logs', 'reversed_by')) {
                $table->dropForeign(['reversed_by']);
                $table->dropColumn('reversed_by');
            }
            if (Schema::hasColumn('customer_deposit_logs', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
        });
    }
};
