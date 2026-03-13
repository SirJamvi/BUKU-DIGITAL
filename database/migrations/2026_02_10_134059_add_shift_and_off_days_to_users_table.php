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
        // Menyimpan ID Shift (Integer). 
        // NOTE: Ini tidak di-foreign key karena tabel shifts ada di database lain.
        $table->integer('shift_id')->nullable()->after('role'); 
        
        // Menyimpan hari libur dalam bentuk JSON (contoh: ["Monday", "Tuesday"])
        $table->json('off_days')->nullable()->after('shift_id');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['shift_id', 'off_days']);
    });
}
};
