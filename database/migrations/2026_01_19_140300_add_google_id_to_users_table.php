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
    Schema::table('users', function (Blueprint $table) {
        // Menambah kolom google_id setelah email
        $table->string('google_id')->nullable()->unique()->after('email');

        // Mengubah kolom password jadi boleh kosong (karena login google tidak pakai password)
        $table->string('password')->nullable()->change();

        // Tambah avatar (opsional)
        $table->string('avatar')->nullable()->after('google_id');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['google_id', 'avatar']);
        $table->string('password')->nullable(false)->change();
    });
}
};
