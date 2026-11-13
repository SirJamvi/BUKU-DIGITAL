<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Helper untuk memeriksa keberadaan indeks pada tabel.
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        try {
            if (DB::getDriverName() === 'mysql') {
                $res = DB::selectOne("
                    SELECT 1 FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND index_name = ?
                ", [$table, $indexName]);

                return (bool) $res;
            }

            $indexes = Schema::getIndexes($table);
            return collect($indexes)->contains('name', $indexName);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. INVENTORY: Tambah kolom cost_price & backfill dari products.cost_price
        if (Schema::hasTable('inventory')) {
            if (!Schema::hasColumn('inventory', 'cost_price')) {
                Schema::table('inventory', function (Blueprint $table) {
                    $table->decimal('cost_price', 15, 2)->default(0)->after('current_stock')->comment('HPP per cabang');
                });
            }

            // Backfill HPP awal dari products.cost_price
            if (DB::getDriverName() === 'mysql') {
                DB::statement("
                    UPDATE inventory i
                    JOIN products p ON i.product_id = p.id
                    SET i.cost_price = COALESCE(p.cost_price, 0)
                    WHERE i.cost_price = 0 OR i.cost_price IS NULL
                ");
            } else {
                // Portable fallback untuk SQLite testing
                $products = DB::table('products')->get(['id', 'cost_price']);
                foreach ($products as $prod) {
                    DB::table('inventory')
                        ->where('product_id', $prod->id)
                        ->where(function ($q) {
                            $q->where('cost_price', 0)->orWhereNull('cost_price');
                        })
                        ->update(['cost_price' => $prod->cost_price ?? 0]);
                }
            }
        }

        // 2. STOCK MOVEMENTS: Update ENUM type, tambah reference_type & unit_cost
        if (Schema::hasTable('stock_movements')) {
            // Perluas ENUM type jika di MySQL
            if (DB::getDriverName() === 'mysql') {
                $col = DB::selectOne("
                    SELECT COLUMN_TYPE 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'stock_movements' 
                      AND COLUMN_NAME = 'type'
                ");

                if ($col && str_contains(strtolower($col->COLUMN_TYPE), 'enum') && !str_contains($col->COLUMN_TYPE, "'sale'")) {
                    DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('in','out','adjustment','waste','sale','transfer_out','transfer_in') NOT NULL");
                }
            }

            Schema::table('stock_movements', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_movements', 'reference_type')) {
                    $table->string('reference_type', 191)->nullable()->after('reference_id')->comment('Model class referensi (e.g. Transaction, Supplier)');
                }

                if (!Schema::hasColumn('stock_movements', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 2)->nullable()->after('quantity')->comment('Snapshot HPP / modal per unit saat mutasi');
                }
            });

            // Index pencarian performa tinggi
            if (!$this->hasIndex('stock_movements', 'stock_movements_biz_branch_prod_created_idx')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->index(['business_id', 'branch_id', 'product_id', 'created_at'], 'stock_movements_biz_branch_prod_created_idx');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_movements')) {
            if ($this->hasIndex('stock_movements', 'stock_movements_biz_branch_prod_created_idx')) {
                Schema::table('stock_movements', function (Blueprint $table) {
                    $table->dropIndex('stock_movements_biz_branch_prod_created_idx');
                });
            }

            Schema::table('stock_movements', function (Blueprint $table) {
                if (Schema::hasColumn('stock_movements', 'unit_cost')) {
                    $table->dropColumn('unit_cost');
                }
                if (Schema::hasColumn('stock_movements', 'reference_type')) {
                    $table->dropColumn('reference_type');
                }
            });

            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('in','out','adjustment','waste') NOT NULL");
            }
        }

        if (Schema::hasTable('inventory') && Schema::hasColumn('inventory', 'cost_price')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};

