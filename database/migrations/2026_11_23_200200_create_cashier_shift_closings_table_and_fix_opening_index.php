<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat tabel cashier_shift_closings dan memastikan index unik cashier_opening_balances per branch.
     */
    public function up(): void
    {
        // 1. Buat tabel cashier_shift_closings
        if (!Schema::hasTable('cashier_shift_closings')) {
            Schema::create('cashier_shift_closings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->index();
                $table->unsignedBigInteger('branch_id')->index();
                $table->unsignedBigInteger('user_id')->index(); // Kasir bertugas
                $table->date('date')->index();
                $table->string('shift_code', 30)->default('default');

                $table->decimal('opening_cash', 15, 2)->default(0.00);
                $table->decimal('cash_sales', 15, 2)->default(0.00);
                $table->decimal('cash_debt_collection', 15, 2)->default(0.00);
                $table->decimal('cash_expenses', 15, 2)->default(0.00);
                $table->decimal('cash_handover_amount', 15, 2)->default(0.00);
                $table->decimal('cash_refunds', 15, 2)->default(0.00);
                $table->decimal('expected_drawer_cash', 15, 2)->default(0.00);
                $table->decimal('actual_cash_counted', 15, 2)->default(0.00);
                $table->decimal('variance', 15, 2)->default(0.00); // actual - expected (Over/Short)
                $table->decimal('ending_cash_for_next_shift', 15, 2)->default(0.00);

                $table->text('notes')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['business_id', 'branch_id', 'user_id', 'date', 'shift_code'],
                    'cashier_shift_closing_unique'
                );
            });
        }

        // 2. Perbaiki unique index pada cashier_opening_balances jika masih memakai skema lama tanpa branch_id
        if (Schema::hasTable('cashier_opening_balances')) {
            $hasBranchCol = Schema::hasColumn('cashier_opening_balances', 'branch_id');
            if ($hasBranchCol) {
                // Drop index lama jika masih ada
                $oldIndexExists = $this->hasIndex('cashier_opening_balances', 'cashier_daily_opening_unique');
                if ($oldIndexExists) {
                    try {
                        Schema::table('cashier_opening_balances', function (Blueprint $table) {
                            $table->dropUnique('cashier_daily_opening_unique');
                        });
                    } catch (\Throwable $e) {
                        // Ignore jika index sudah tidak ada di level DB
                    }
                }

                // Tambahkan index per cabang jika belum ada
                $branchIndexExists = $this->hasIndex('cashier_opening_balances', 'cashier_branch_daily_opening_unique');
                if (!$branchIndexExists) {
                    try {
                        Schema::table('cashier_opening_balances', function (Blueprint $table) {
                            $table->unique(
                                ['business_id', 'branch_id', 'user_id', 'date'],
                                'cashier_branch_daily_opening_unique'
                            );
                        });
                    } catch (\Throwable $e) {
                        // Abaikan jika sudah ada
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_shift_closings');
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        try {
            $results = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($results);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
