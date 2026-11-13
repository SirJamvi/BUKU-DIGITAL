<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('owner_expenses') && !Schema::hasColumn('owner_expenses', 'business_id')) {
            Schema::table('owner_expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('business_id')
                    ->nullable()
                    ->after('id')
                    ->index();

                $table->foreign('business_id')
                    ->references('id')
                    ->on('businesses')
                    ->onDelete('restrict');
            });

            // Backfill via created_by -> users.business_id
            DB::statement("UPDATE owner_expenses oe JOIN users u ON oe.created_by = u.id SET oe.business_id = u.business_id WHERE oe.business_id IS NULL AND u.business_id IS NOT NULL");

            $orphan = DB::table('owner_expenses')->whereNull('business_id')->count();
            if ($orphan > 0) {
                Log::warning("Migration add_business_id_to_owner_expenses: {$orphan} rows have NULL business_id");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('owner_expenses') && Schema::hasColumn('owner_expenses', 'business_id')) {
            Schema::table('owner_expenses', function (Blueprint $table) {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            });
        }
    }
};

