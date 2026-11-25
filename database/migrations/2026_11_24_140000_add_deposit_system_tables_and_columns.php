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
        // 1. Tambah kolom deposit_balance ke tabel customers
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'deposit_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('deposit_balance', 15, 2)->default(0.00)->after('loyalty_points');
            });
        }

        // 2. Buat tabel ledger audit customer_deposit_logs
        if (!Schema::hasTable('customer_deposit_logs')) {
            Schema::create('customer_deposit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
                $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
                $table->foreignId('cash_flow_id')->nullable()->constrained('cash_flow')->onDelete('set null');
                $table->enum('type', ['credit', 'debit', 'void_restore', 'refund']);
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_before', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('payment_method', 50)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['business_id', 'customer_id'], 'cdl_business_customer_idx');
                $table->index(['branch_id', 'created_at'], 'cdl_branch_created_idx');
                $table->index('transaction_id', 'cdl_transaction_idx');
            });
        }

        // 3. Tambah kolom deposit_used dan net_payable_amount ke tabel transactions
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'deposit_used')) {
                    $table->decimal('deposit_used', 15, 2)->default(0.00)->after('total_amount');
                }
                if (!Schema::hasColumn('transactions', 'net_payable_amount')) {
                    $table->decimal('net_payable_amount', 15, 2)->nullable()->after('deposit_used');
                }
            });

            // Backfill data historis agar net_payable_amount = total_amount jika masih NULL
            DB::table('transactions')
                ->whereNull('net_payable_amount')
                ->update([
                    'net_payable_amount' => DB::raw('total_amount')
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
                if (Schema::hasColumn('transactions', 'net_payable_amount')) {
                    $table->dropColumn('net_payable_amount');
                }
                if (Schema::hasColumn('transactions', 'deposit_used')) {
                    $table->dropColumn('deposit_used');
                }
            });
        }

        Schema::dropIfExists('customer_deposit_logs');

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'deposit_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('deposit_balance');
            });
        }
    }
};
