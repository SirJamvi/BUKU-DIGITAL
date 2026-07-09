<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Cek dulu apakah kolom shift_id belum ada, baru buat
            if (!Schema::hasColumn('users', 'shift_id')) {
                $table->integer('shift_id')->nullable()->after('role');
            }
            
            // Cek dulu apakah kolom off_days belum ada, baru buat
            if (!Schema::hasColumn('users', 'off_days')) {
                $table->json('off_days')->nullable()->after('shift_id');
            }
        });
    }

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['shift_id', 'off_days']);
    });
}
};
