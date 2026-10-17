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
        if (!Schema::hasColumn('transaction_details', 'cost_price')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->decimal('cost_price', 15, 2)->nullable()->after('quantity');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('transaction_details', 'cost_price')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
