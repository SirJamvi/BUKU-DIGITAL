<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom pelacakan histori kredit/kasbon dan audit void pada tabel transactions.
     */
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'initial_payment_method')) {
                    $table->string('initial_payment_method', 50)->nullable()->after('payment_method');
                }
                if (!Schema::hasColumn('transactions', 'settlement_payment_method')) {
                    $table->string('settlement_payment_method', 50)->nullable()->after('initial_payment_method');
                }
                if (!Schema::hasColumn('transactions', 'settled_at')) {
                    $table->timestamp('settled_at')->nullable()->after('settlement_payment_method');
                }
                if (!Schema::hasColumn('transactions', 'void_reason')) {
                    $table->text('void_reason')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('transactions', 'voided_by')) {
                    $table->unsignedBigInteger('voided_by')->nullable()->after('void_reason')->index();
                }
                if (!Schema::hasColumn('transactions', 'voided_at')) {
                    $table->timestamp('voided_at')->nullable()->after('voided_by');
                }
            });

            // Backfill initial_payment_method
            // 1. Transaksi yang memiliki catatan pelunasan di cash_flow aslinya berstatus kasbon
            if (Schema::hasTable('cash_flow')) {
                $settledTxIds = DB::table('cash_flow')
                    ->where(function ($q) {
                        $q->where('flow_type', 'debt_settlement')
                          ->orWhere('description', 'LIKE', '%Pelunasan%');
                    })
                    ->whereNotNull('reference_id')
                    ->pluck('reference_id')
                    ->filter(fn($id) => is_numeric($id))
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->all();

                if (!empty($settledTxIds)) {
                    DB::table('transactions')
                        ->whereIn('id', $settledTxIds)
                        ->update([
                            'initial_payment_method'    => 'kasbon',
                            'settlement_payment_method' => DB::raw('payment_method'),
                            'settled_at'                => DB::raw('updated_at'),
                        ]);
                }
            }

            // 2. Transaksi lainnya menyalin metode bayar langsung ke initial_payment_method
            DB::table('transactions')
                ->whereNull('initial_payment_method')
                ->update([
                    'initial_payment_method' => DB::raw('payment_method'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $columns = [
                    'initial_payment_method',
                    'settlement_payment_method',
                    'settled_at',
                    'void_reason',
                    'voided_by',
                    'voided_at',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('transactions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
