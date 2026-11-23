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
        if (Schema::hasTable('cashier_shift_closings')) {
            Schema::table('cashier_shift_closings', function (Blueprint $table) {
                if (!Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
                    $table->string('external_reference', 100)->nullable()->unique()->after('shift_code');
                }
                if (!Schema::hasColumn('cashier_shift_closings', 'handover_receiver')) {
                    $table->string('handover_receiver', 150)->nullable()->after('ending_cash_for_next_shift');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cashier_shift_closings')) {
            Schema::table('cashier_shift_closings', function (Blueprint $table) {
                if (Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
                    $table->dropUnique(['external_reference']);
                    $table->dropColumn('external_reference');
                }
                if (Schema::hasColumn('cashier_shift_closings', 'handover_receiver')) {
                    $table->dropColumn('handover_receiver');
                }
            });
        }
    }
};
