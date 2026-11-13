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
        // 1. Buat tabel branches (Unit Cabang / Sub-Tenant)
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->onDelete('cascade');
                $table->string('name', 191)->comment('Nama Cabang');
                $table->string('code', 50)->comment('Kode Cabang unik per bisnis, misal: PUSAT, CB-01');
                $table->boolean('is_main')->default(false)->comment('Apakah cabang ini adalah cabang pusat');
                $table->text('address')->nullable();
                $table->string('phone', 50)->nullable();
                
                // Integrasi WhatsApp AI Fonnte per cabang (Isolated Mapping)
                $table->string('fonnte_token', 191)->nullable()->comment('Token Fonnte device spesifik cabang');
                $table->string('fonnte_device_id', 100)->nullable()->comment('ID Device Fonnte cabang');

                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Unique code dalam lingkup 1 bisnis
                $table->unique(['business_id', 'code'], 'branches_business_code_unique');
                $table->index(['business_id', 'is_active'], 'branches_lookup_idx');
                $table->index(['business_id', 'is_main'], 'branches_business_main_idx');
                $table->index('fonnte_token', 'branches_fonnte_token_idx');
            });
        }

        // 2. Buat tabel pivot branch_user (Staff Mobility: Many-to-Many Kasir/Driver)
        if (!Schema::hasTable('branch_user')) {
            Schema::create('branch_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->boolean('is_default')->default(false)->comment('Apakah cabang ini menjadi cabang default staf');
                $table->timestamps();

                $table->unique(['user_id', 'branch_id'], 'branch_user_unique');
                $table->index(['user_id', 'is_default'], 'branch_user_default_idx');
            });
        }

        // 3. Tambahkan branch_id (primary/default branch reference) ke tabel users
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->nullOnDelete()
                    ->comment('Cabang default/kenyamanan staf (nullable, admin selalu null)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};
