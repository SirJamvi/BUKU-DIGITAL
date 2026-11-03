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
        if (!Schema::hasTable('customer_phones')) {
            Schema::create('customer_phones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('phone', 30)->index();
                $table->string('label', 50)->nullable()->comment('Label nomor: Utama, Kasir, Owner, Toko, Supir, dsb');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->index(['customer_id', 'phone']);
            });

            // Sinkronkan nomor telepon eksisting dari tabel customers
            if (Schema::hasColumn('customers', 'phone')) {
                $existingCustomers = DB::table('customers')
                    ->whereNotNull('phone')
                    ->where('phone', '!=', '')
                    ->select('id', 'phone')
                    ->get();

                foreach ($existingCustomers as $cust) {
                    DB::table('customer_phones')->insert([
                        'customer_id' => $cust->id,
                        'phone'       => $cust->phone,
                        'label'       => 'Utama',
                        'is_primary'  => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_phones');
    }
};
