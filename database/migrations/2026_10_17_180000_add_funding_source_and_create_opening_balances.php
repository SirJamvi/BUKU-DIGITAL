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
        // 1. Tambah kolom funding_source dan affects_drawer pada tabel cash_flow
        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (!Schema::hasColumn('cash_flow', 'funding_source')) {
                    $table->string('funding_source', 50)->default('drawer')->after('payment_method');
                }
                if (!Schema::hasColumn('cash_flow', 'affects_drawer')) {
                    $table->boolean('affects_drawer')->default(true)->after('funding_source');
                }
            });
        }

        // 2. Buat tabel modal awal laci kasir (kas kemarin)
        if (!Schema::hasTable('cashier_opening_balances')) {
            Schema::create('cashier_opening_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('date')->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'user_id', 'date'], 'cashier_daily_opening_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cashier_opening_balances')) {
            Schema::dropIfExists('cashier_opening_balances');
        }

        if (Schema::hasTable('cash_flow')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                if (Schema::hasColumn('cash_flow', 'affects_drawer')) {
                    $table->dropColumn('affects_drawer');
                }
                if (Schema::hasColumn('cash_flow', 'funding_source')) {
                    $table->dropColumn('funding_source');
                }
            });
        }
    }
};
