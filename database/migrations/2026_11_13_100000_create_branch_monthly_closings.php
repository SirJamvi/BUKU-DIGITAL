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
        if (!Schema::hasTable('branch_monthly_closings')) {
            Schema::create('branch_monthly_closings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedTinyInteger('period_month');
                $table->unsignedSmallInteger('period_year');

                $table->decimal('monthly_income', 15, 2)->default(0);
                $table->decimal('monthly_cogs', 15, 2)->default(0);
                $table->decimal('gross_profit', 15, 2)->default(0);
                $table->decimal('monthly_expense', 15, 2)->default(0);
                $table->decimal('net_profit', 15, 2)->default(0);

                $table->string('status', 20)->default('completed');
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('business_id')
                    ->references('id')
                    ->on('businesses')
                    ->onDelete('cascade');

                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('cascade');

                $table->foreign('closed_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                $table->unique(
                    ['business_id', 'branch_id', 'period_month', 'period_year'],
                    'branch_monthly_closings_biz_branch_period_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_monthly_closings');
    }
};

