<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Helper untuk mengambil foreign key constraint dan delete_rule pada suatu kolom.
     */
    protected function getForeignKeyConstraint(string $table, string $column): ?object
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        try {
            if (DB::getDriverName() === 'mysql') {
                return DB::selectOne("
                    SELECT kcu.constraint_name, rc.delete_rule
                    FROM information_schema.key_column_usage kcu
                    JOIN information_schema.referential_constraints rc
                      ON kcu.constraint_name = rc.constraint_name
                     AND kcu.constraint_schema = rc.constraint_schema
                    WHERE kcu.table_schema = DATABASE()
                      AND kcu.table_name = ?
                      AND kcu.column_name = ?
                      AND kcu.referenced_table_name IS NOT NULL
                ", [$table, $column]);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // =========================================================================
        // 1. BRANCHES: Tambah is_main, rename default 'Cabang Utama' -> name bisnis
        // =========================================================================
        if (Schema::hasTable('branches')) {
            if (!Schema::hasColumn('branches', 'is_main')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->boolean('is_main')->default(false)->after('is_active')->comment('Apakah cabang ini adalah cabang pusat');
                });
            }

            if (!$this->hasIndex('branches', 'branches_business_main_idx')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->index(['business_id', 'is_main'], 'branches_business_main_idx');
                });
            }

            // Set is_main = 1 untuk cabang code 'CB-PUSAT' per business.
            // Rename hanya baris yang masih bernilai default backfill lama: name = businesses.name, code = 'PUSAT'.
            $businesses = DB::table('businesses')->get();
            foreach ($businesses as $biz) {
                $cbPusat = DB::table('branches')
                    ->where('business_id', $biz->id)
                    ->where('code', 'CB-PUSAT')
                    ->first();

                if ($cbPusat) {
                    $updateData = ['is_main' => 1];
                    // Rename jika masih memakai nama default migrasi lama
                    if ($cbPusat->name === 'Cabang Utama') {
                        $updateData['name'] = $biz->name;
                        $updateData['code'] = 'PUSAT';
                    } else {
                        // Jika sudah ada nama custom, hanya ubah kodenya menjadi PUSAT
                        $updateData['code'] = 'PUSAT';
                    }

                    DB::table('branches')->where('id', $cbPusat->id)->update($updateData);
                } else {
                    // Cek jika sudah bernama 'PUSAT' tapi belum is_main = 1
                    $pusat = DB::table('branches')
                        ->where('business_id', $biz->id)
                        ->where('code', 'PUSAT')
                        ->first();

                    if ($pusat && !$pusat->is_main) {
                        DB::table('branches')->where('id', $pusat->id)->update(['is_main' => 1]);
                    }
                }
            }
        }

        // =========================================================================
        // 2. FOREIGN KEYS: Ganti cascade -> restrict pada tabel operasional
        // =========================================================================
        $tablesToRestrict = [
            'inventory',
            'transactions',
            'stock_movements',
            'cash_flow',
            'cashier_opening_balances',
            'opname_sessions',
        ];

        foreach ($tablesToRestrict as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'branch_id')) {
                $fk = $this->getForeignKeyConstraint($tbl, 'branch_id');

                if ($fk && strtoupper((string)$fk->delete_rule) === 'CASCADE') {
                    $fkName = $fk->constraint_name;
                    Schema::table($tbl, function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                        $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
                    });
                } elseif (!$fk && DB::getDriverName() === 'mysql') {
                    Schema::table($tbl, function (Blueprint $table) {
                        $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
                    });
                }
            }
        }

        // =========================================================================
        // 3. UNIQUE / INDEX CONSTRAINTS & DUPLICATE CHECKS
        // =========================================================================

        // A. INVENTORY: UNIQUE(business_id, branch_id, product_id)
        if (Schema::hasTable('inventory') && Schema::hasColumn('inventory', 'branch_id')) {
            $invDups = DB::table('inventory')
                ->select('business_id', 'branch_id', 'product_id', DB::raw('COUNT(*) as total'))
                ->groupBy('business_id', 'branch_id', 'product_id')
                ->having('total', '>', 1)
                ->get();

            if ($invDups->isNotEmpty()) {
                $details = $invDups->map(fn($d) => "business={$d->business_id}, branch={$d->branch_id}, product={$d->product_id} (x{$d->total})")->implode(', ');
                throw new \RuntimeException("ABORT MIGRATION: Ditemukan duplikat pada tabel inventory untuk kunci (business_id, branch_id, product_id): [{$details}]. Data tidak dihapus.");
            }

            if (!$this->hasIndex('inventory', 'inventory_business_branch_product_unique')) {
                Schema::table('inventory', function (Blueprint $table) {
                    $table->unique(['business_id', 'branch_id', 'product_id'], 'inventory_business_branch_product_unique');
                });
            }
        }

        // B. CASHIER OPENING BALANCES: UNIQUE(business_id, branch_id, user_id, date)
        if (Schema::hasTable('cashier_opening_balances') && Schema::hasColumn('cashier_opening_balances', 'branch_id')) {
            $cobDups = DB::table('cashier_opening_balances')
                ->select('business_id', 'branch_id', 'user_id', 'date', DB::raw('COUNT(*) as total'))
                ->groupBy('business_id', 'branch_id', 'user_id', 'date')
                ->having('total', '>', 1)
                ->get();

            if ($cobDups->isNotEmpty()) {
                $details = $cobDups->map(fn($d) => "business={$d->business_id}, branch={$d->branch_id}, user={$d->user_id}, date={$d->date} (x{$d->total})")->implode(', ');
                throw new \RuntimeException("ABORT MIGRATION: Ditemukan duplikat pada tabel cashier_opening_balances untuk kunci (business_id, branch_id, user_id, date): [{$details}]. Data tidak dihapus.");
            }

            // Urutan aman: buat index baru dulu sebelum drop index lama
            if (!$this->hasIndex('cashier_opening_balances', 'cashier_branch_daily_opening_unique')) {
                Schema::table('cashier_opening_balances', function (Blueprint $table) {
                    $table->unique(['business_id', 'branch_id', 'user_id', 'date'], 'cashier_branch_daily_opening_unique');
                });
            }

            if ($this->hasIndex('cashier_opening_balances', 'cashier_daily_opening_unique')) {
                Schema::table('cashier_opening_balances', function (Blueprint $table) {
                    $table->dropUnique('cashier_daily_opening_unique');
                });
            }
        }

        // C. OPNAME SESSIONS: INDEX(business_id, branch_id, created_at)
        if (Schema::hasTable('opname_sessions') && Schema::hasColumn('opname_sessions', 'branch_id')) {
            if (!$this->hasIndex('opname_sessions', 'opname_sessions_business_branch_created_idx')) {
                Schema::table('opname_sessions', function (Blueprint $table) {
                    $table->index(['business_id', 'branch_id', 'created_at'], 'opname_sessions_business_branch_created_idx');
                });
            }
        }

        // =========================================================================
        // 4. AI ORDER DRAFTS, USER ACTIVITY LOGS, USER SESSIONS
        // =========================================================================

        // A. AI ORDER DRAFTS
        if (Schema::hasTable('ai_order_drafts')) {
            if (!Schema::hasColumn('ai_order_drafts', 'business_id')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->onDelete('cascade');
                });
            }

            if (!Schema::hasColumn('ai_order_drafts', 'branch_id')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->foreignId('branch_id')->nullable()->after('business_id')->constrained('branches')->onDelete('restrict');
                });
            }

            // Backfill ai_order_drafts dari customer terkait
            $unassignedDrafts = 0;
            $drafts = DB::table('ai_order_drafts')->whereNull('branch_id')->get();
            foreach ($drafts as $draft) {
                $cust = null;
                if (!empty($draft->sender_phone)) {
                    $cust = DB::table('customers')->where('phone', $draft->sender_phone)->first();
                    if (!$cust && Schema::hasTable('customer_phones')) {
                        $p = DB::table('customer_phones')->where('phone', $draft->sender_phone)->first();
                        if ($p) {
                            $cust = DB::table('customers')->where('id', $p->customer_id)->first();
                        }
                    }
                }

                if ($cust && !empty($cust->business_id)) {
                    $mainBranchId = DB::table('branches')
                        ->where('business_id', $cust->business_id)
                        ->where('is_main', 1)
                        ->value('id');

                    DB::table('ai_order_drafts')->where('id', $draft->id)->update([
                        'business_id' => $cust->business_id,
                        'branch_id'   => $mainBranchId,
                    ]);
                } else {
                    $unassignedDrafts++;
                }
            }

            if ($unassignedDrafts > 0) {
                Log::info("[Fase1Corrective] ai_order_drafts dengan customer tidak teridentifikasi: {$unassignedDrafts} baris.");
            }

            // Unique index: ganti sender_phone menjadi (branch_id, sender_phone)
            $draftDups = DB::table('ai_order_drafts')
                ->select('branch_id', 'sender_phone', DB::raw('COUNT(*) as total'))
                ->whereNotNull('branch_id')
                ->groupBy('branch_id', 'sender_phone')
                ->having('total', '>', 1)
                ->get();

            if ($draftDups->isNotEmpty()) {
                $details = $draftDups->map(fn($d) => "branch={$d->branch_id}, phone={$d->sender_phone} (x{$d->total})")->implode(', ');
                throw new \RuntimeException("ABORT MIGRATION: Ditemukan duplikat pada tabel ai_order_drafts untuk kunci (branch_id, sender_phone): [{$details}]. Data tidak dihapus.");
            }

            if (!$this->hasIndex('ai_order_drafts', 'ai_order_drafts_branch_phone_unique')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->unique(['branch_id', 'sender_phone'], 'ai_order_drafts_branch_phone_unique');
                });
            }

            if ($this->hasIndex('ai_order_drafts', 'ai_order_drafts_sender_phone_unique')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->dropUnique('ai_order_drafts_sender_phone_unique');
                });
            }
        }

        // B. USER ACTIVITY LOGS & USER SESSIONS
        foreach (['user_activity_logs', 'user_sessions'] as $table) {
            if (Schema::hasTable($table)) {
                if (!Schema::hasColumn($table, 'branch_id')) {
                    Schema::table($table, function (Blueprint $t) {
                        $t->foreignId('branch_id')->nullable()->after('business_id')->constrained('branches')->onDelete('restrict');
                    });
                }

                // Backfill log dan sesi lama -> cabang is_main dari business-nya secara chunking
                $businesses = DB::table('businesses')->get();
                foreach ($businesses as $biz) {
                    $mainBranchId = DB::table('branches')
                        ->where('business_id', $biz->id)
                        ->where('is_main', 1)
                        ->value('id');

                    if ($mainBranchId) {
                        while (true) {
                            $affected = DB::table($table)
                                ->where('business_id', $biz->id)
                                ->whereNull('branch_id')
                                ->limit(500)
                                ->update(['branch_id' => $mainBranchId]);

                            if ($affected === 0) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // =========================================================================
        // 5. PERBAIKI TEMPORARY ORDERS
        // =========================================================================
        if (Schema::hasTable('temporary_orders')) {
            $tempOrders = DB::table('temporary_orders')->get();
            $correctedTempOrders = 0;
            $unverifiedTempOrders = 0;

            foreach ($tempOrders as $order) {
                $cust = null;
                if (!empty($order->customer_id)) {
                    $cust = DB::table('customers')->where('id', $order->customer_id)->first();
                }
                if (!$cust && !empty($order->sender_phone)) {
                    $cust = DB::table('customers')->where('phone', $order->sender_phone)->first();
                    if (!$cust && Schema::hasTable('customer_phones')) {
                        $p = DB::table('customer_phones')->where('phone', $order->sender_phone)->first();
                        if ($p) {
                            $cust = DB::table('customers')->where('id', $p->customer_id)->first();
                        }
                    }
                }

                if ($cust && !empty($cust->business_id)) {
                    $actualBusinessId = $cust->business_id;
                    $mainBranchId = DB::table('branches')
                        ->where('business_id', $actualBusinessId)
                        ->where('is_main', 1)
                        ->value('id');

                    // Koreksi HANYA jika customer membuktikan business lain atau branch_id kosong
                    if ($order->business_id != $actualBusinessId || empty($order->branch_id)) {
                        DB::table('temporary_orders')->where('id', $order->id)->update([
                            'business_id' => $actualBusinessId,
                            'branch_id'   => $mainBranchId,
                        ]);
                        $correctedTempOrders++;
                    }
                } else {
                    // Jika customer tidak ditemukan, jangan asumsikan salah, biarkan data aslinya
                    $unverifiedTempOrders++;
                }
            }

            Log::info("[Fase1Corrective] temporary_orders: {$correctedTempOrders} baris dikoreksi, {$unverifiedTempOrders} baris tanpa customer pembukti dipertahankan.");
        }

        // =========================================================================
        // 6. USERS & BRANCH_USER
        // =========================================================================
        if (Schema::hasTable('users')) {
            // Admin dan Owner: branch_id harus NULL
            DB::table('users')
                ->whereIn('role', ['admin', 'owner'])
                ->whereNotNull('branch_id')
                ->update(['branch_id' => null]);

            // Non-Admin: isi branch_user (is_default=1) dari users.branch_id jika belum ada barisnya
            if (Schema::hasTable('branch_user')) {
                $nonAdmins = DB::table('users')
                    ->whereNotIn('role', ['admin', 'owner'])
                    ->get();

                foreach ($nonAdmins as $u) {
                    $branchId = $u->branch_id;

                    if (!$branchId && $u->business_id) {
                        $branchId = DB::table('branches')
                            ->where('business_id', $u->business_id)
                            ->where('is_main', 1)
                            ->value('id');

                        if ($branchId) {
                            DB::table('users')->where('id', $u->id)->update(['branch_id' => $branchId]);
                        }
                    }

                    if ($branchId) {
                        $hasPivot = DB::table('branch_user')
                            ->where('user_id', $u->id)
                            ->where('branch_id', $branchId)
                            ->exists();

                        if (!$hasPivot) {
                            DB::table('branch_user')->insert([
                                'user_id'    => $u->id,
                                'branch_id'  => $branchId,
                                'is_default' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rollback tabel users & pivot tidak membuang data pengguna, tetapi membersihkan penambahan migrasi ini bila diperlukan

        // 2. Rollback user_activity_logs & user_sessions
        foreach (['user_activity_logs', 'user_sessions'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                $fk = $this->getForeignKeyConstraint($table, 'branch_id');
                if ($fk) {
                    Schema::table($table, function (Blueprint $t) use ($fk) {
                        $t->dropForeign($fk->constraint_name);
                    });
                }
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('branch_id');
                });
            }
        }

        // 3. Rollback ai_order_drafts
        if (Schema::hasTable('ai_order_drafts')) {
            if ($this->hasIndex('ai_order_drafts', 'ai_order_drafts_branch_phone_unique')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->dropUnique('ai_order_drafts_branch_phone_unique');
                });
            }
            if (!$this->hasIndex('ai_order_drafts', 'ai_order_drafts_sender_phone_unique')) {
                Schema::table('ai_order_drafts', function (Blueprint $table) {
                    $table->unique('sender_phone', 'ai_order_drafts_sender_phone_unique');
                });
            }
            if (Schema::hasColumn('ai_order_drafts', 'branch_id')) {
                $fk = $this->getForeignKeyConstraint('ai_order_drafts', 'branch_id');
                if ($fk) {
                    Schema::table('ai_order_drafts', function (Blueprint $t) use ($fk) {
                        $t->dropForeign($fk->constraint_name);
                    });
                }
                Schema::table('ai_order_drafts', function (Blueprint $t) {
                    $t->dropColumn('branch_id');
                });
            }
            if (Schema::hasColumn('ai_order_drafts', 'business_id')) {
                $fk = $this->getForeignKeyConstraint('ai_order_drafts', 'business_id');
                if ($fk) {
                    Schema::table('ai_order_drafts', function (Blueprint $t) use ($fk) {
                        $t->dropForeign($fk->constraint_name);
                    });
                }
                Schema::table('ai_order_drafts', function (Blueprint $t) {
                    $t->dropColumn('business_id');
                });
            }
        }

        // 4. Rollback opname_sessions index
        if ($this->hasIndex('opname_sessions', 'opname_sessions_business_branch_created_idx')) {
            Schema::table('opname_sessions', function (Blueprint $table) {
                $table->dropIndex('opname_sessions_business_branch_created_idx');
            });
        }

        // 5. Rollback cashier_opening_balances unique
        if ($this->hasIndex('cashier_opening_balances', 'cashier_branch_daily_opening_unique')) {
            if (!$this->hasIndex('cashier_opening_balances', 'cashier_daily_opening_unique')) {
                Schema::table('cashier_opening_balances', function (Blueprint $table) {
                    $table->unique(['business_id', 'user_id', 'date'], 'cashier_daily_opening_unique');
                });
            }
            Schema::table('cashier_opening_balances', function (Blueprint $table) {
                $table->dropUnique('cashier_branch_daily_opening_unique');
            });
        }

        // 6. Rollback inventory unique
        if ($this->hasIndex('inventory', 'inventory_business_branch_product_unique')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->dropUnique('inventory_business_branch_product_unique');
            });
        }

        // 7. Rollback FK branch_id dari restrict kembali ke cascade
        $tablesToCascade = [
            'inventory',
            'transactions',
            'stock_movements',
            'cash_flow',
            'cashier_opening_balances',
            'opname_sessions',
        ];

        foreach ($tablesToCascade as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'branch_id')) {
                $fk = $this->getForeignKeyConstraint($tbl, 'branch_id');
                if ($fk) {
                    $fkName = $fk->constraint_name;
                    Schema::table($tbl, function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                        $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
                    });
                }
            }
        }

        // 8. Rollback branches.is_main
        if (Schema::hasTable('branches')) {
            if ($this->hasIndex('branches', 'branches_business_main_idx')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->dropIndex('branches_business_main_idx');
                });
            }
            if (Schema::hasColumn('branches', 'is_main')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->dropColumn('is_main');
                });
            }
        }
    }
};

