<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Helper untuk memeriksa keberadaan indeks pada tabel di Laravel 11.
     */
    protected function hasIndex(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        try {
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
        // -------------------------------------------------------------
        // 1. TAMBAHKAN KOLOM branch_id & business_id KE TABEL OPERASIONAL
        //    (FK restrict agar cabang tidak bisa dihapus jika ada data)
        // -------------------------------------------------------------

        // A. INVENTORY
        if (Schema::hasTable('inventory') && !Schema::hasColumn('inventory', 'branch_id')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->onDelete('restrict');
            });
        }

        // B. TRANSACTIONS
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->onDelete('restrict');
            });
        }

        // C. STOCK MOVEMENTS (dengan destination_branch_id untuk mutasi transfer)
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_movements', 'branch_id')) {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('business_id')
                        ->constrained('branches')
                        ->onDelete('restrict');
                }
                if (!Schema::hasColumn('stock_movements', 'destination_branch_id')) {
                    $table->foreignId('destination_branch_id')
                        ->nullable()
                        ->after('branch_id')
                        ->constrained('branches')
                        ->nullOnDelete()
                        ->comment('Cabang tujuan jika tipe mutasi adalah transfer');
                }
            });
        }

        // D. CASH FLOW
        if (Schema::hasTable('cash_flow') && !Schema::hasColumn('cash_flow', 'branch_id')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->onDelete('restrict');
            });
        }

        // E. CASHIER OPENING BALANCES
        if (Schema::hasTable('cashier_opening_balances') && !Schema::hasColumn('cashier_opening_balances', 'branch_id')) {
            Schema::table('cashier_opening_balances', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->onDelete('restrict');
            });
        }

        // F. OPNAME SESSIONS
        if (Schema::hasTable('opname_sessions') && !Schema::hasColumn('opname_sessions', 'branch_id')) {
            Schema::table('opname_sessions', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->onDelete('restrict');
            });
        }

        // G. TEMPORARY ORDERS (AI WhatsApp Orders)
        if (Schema::hasTable('temporary_orders')) {
            Schema::table('temporary_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('temporary_orders', 'business_id')) {
                    $table->foreignId('business_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('businesses')
                        ->onDelete('cascade');
                }
                if (!Schema::hasColumn('temporary_orders', 'branch_id')) {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('business_id')
                        ->constrained('branches')
                        ->onDelete('restrict');
                }
            });
        }

        // H. AI ORDER DRAFTS (Draft Percakapan WhatsApp)
        if (Schema::hasTable('ai_order_drafts')) {
            Schema::table('ai_order_drafts', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_order_drafts', 'business_id')) {
                    $table->foreignId('business_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('businesses')
                        ->onDelete('cascade');
                }
                if (!Schema::hasColumn('ai_order_drafts', 'branch_id')) {
                    $table->foreignId('branch_id')
                        ->nullable()
                        ->after('business_id')
                        ->constrained('branches')
                        ->onDelete('restrict');
                }
            });
        }

        // I. USER ACTIVITY LOGS & USER SESSIONS (Informasional, nullOnDelete)
        if (Schema::hasTable('user_activity_logs') && !Schema::hasColumn('user_activity_logs', 'branch_id')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('user_sessions') && !Schema::hasColumn('user_sessions', 'branch_id')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        // J. OWNER EXPENSES (Verifikasi & Tambah business_id jika belum ada)
        if (Schema::hasTable('owner_expenses') && !Schema::hasColumn('owner_expenses', 'business_id')) {
            Schema::table('owner_expenses', function (Blueprint $table) {
                $table->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('businesses')
                    ->onDelete('cascade');
            });
        }

        // -------------------------------------------------------------
        // 2. BACKFILL DATA DALAM TRANSAKSI PER-BUSINESS
        // -------------------------------------------------------------
        if (Schema::hasTable('businesses')) {
            $businesses = DB::table('businesses')->get();

            foreach ($businesses as $biz) {
                DB::transaction(function () use ($biz) {
                    // Cari cabang utama (is_main = 1)
                    $mainBranch = DB::table('branches')
                        ->where('business_id', $biz->id)
                        ->where('is_main', 1)
                        ->first();

                    if (!$mainBranch) {
                        // Cek apakah ada cabang lain yang sudah dibuat sebelumnya
                        $anyBranch = DB::table('branches')
                            ->where('business_id', $biz->id)
                            ->first();

                        if ($anyBranch) {
                            DB::table('branches')->where('id', $anyBranch->id)->update([
                                'name'      => $biz->name,
                                'code'      => 'PUSAT',
                                'is_main'   => 1,
                                'address'   => $biz->address,
                                'phone'     => $biz->phone,
                                'manager_id'=> $biz->owner_id,
                                'updated_at'=> now(),
                            ]);
                            $branchId = $anyBranch->id;
                        } else {
                            $branchId = DB::table('branches')->insertGetId([
                                'business_id' => $biz->id,
                                'name'        => $biz->name,
                                'code'        => 'PUSAT',
                                'is_main'     => 1,
                                'address'     => $biz->address,
                                'phone'       => $biz->phone,
                                'manager_id'  => $biz->owner_id,
                                'is_active'   => 1,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ]);
                        }
                    } else {
                        $branchId = $mainBranch->id;
                    }

                    // Backfill tabel operasional untuk bisnis ini
                    $operational = [
                        'inventory',
                        'transactions',
                        'stock_movements',
                        'cash_flow',
                        'cashier_opening_balances',
                        'opname_sessions',
                        'user_activity_logs',
                        'user_sessions',
                    ];

                    foreach ($operational as $table) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                            DB::table($table)
                                ->where('business_id', $biz->id)
                                ->whereNull('branch_id')
                                ->update(['branch_id' => $branchId]);
                        }
                    }

                    // Backfill owner_expenses jika ada
                    if (Schema::hasTable('owner_expenses') && Schema::hasColumn('owner_expenses', 'business_id')) {
                        DB::table('owner_expenses')
                            ->whereNull('business_id')
                            ->whereIn('created_by', function ($query) use ($biz) {
                                $query->select('id')->from('users')->where('business_id', $biz->id);
                            })
                            ->update(['business_id' => $biz->id]);
                    }

                    // Backfill USERS:
                    // Staf non-admin diisi branch_id dan pivot branch_user.
                    // Admin/Owner dibiarkan NULL tanpa pivot.
                    $staffUsers = DB::table('users')
                        ->where('business_id', $biz->id)
                        ->whereNotIn('role', ['admin', 'owner'])
                        ->get();

                    foreach ($staffUsers as $staff) {
                        DB::table('users')
                            ->where('id', $staff->id)
                            ->whereNull('branch_id')
                            ->update(['branch_id' => $branchId]);

                        $hasPivot = DB::table('branch_user')
                            ->where('user_id', $staff->id)
                            ->where('branch_id', $branchId)
                            ->exists();

                        if (!$hasPivot) {
                            DB::table('branch_user')->insert([
                                'user_id'    => $staff->id,
                                'branch_id'  => $branchId,
                                'is_default' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Pastikan admin/owner tidak punya pivot default
                    $adminUserIds = DB::table('users')
                        ->where('business_id', $biz->id)
                        ->whereIn('role', ['admin', 'owner'])
                        ->pluck('id');

                    if ($adminUserIds->isNotEmpty()) {
                        DB::table('users')
                            ->whereIn('id', $adminUserIds)
                            ->update(['branch_id' => null]);
                    }
                });
            }
        }

        // Backfill temporary_orders & ai_order_drafts berdasarkan customer terkait
        $unassignedTempOrders = 0;
        if (Schema::hasTable('temporary_orders')) {
            $tempOrders = DB::table('temporary_orders')->whereNull('business_id')->get();
            foreach ($tempOrders as $order) {
                $cust = null;
                if (!empty($order->customer_id)) {
                    $cust = DB::table('customers')->where('id', $order->customer_id)->first();
                }
                if (!$cust && !empty($order->sender_phone)) {
                    $cust = DB::table('customers')->where('phone', $order->sender_phone)->first();
                    if (!$cust && Schema::hasTable('customer_phones')) {
                        $phoneRow = DB::table('customer_phones')->where('phone', $order->sender_phone)->first();
                        if ($phoneRow) {
                            $cust = DB::table('customers')->where('id', $phoneRow->customer_id)->first();
                        }
                    }
                }

                if ($cust && !empty($cust->business_id)) {
                    $targetBranch = DB::table('branches')
                        ->where('business_id', $cust->business_id)
                        ->where('is_main', 1)
                        ->first();

                    DB::table('temporary_orders')->where('id', $order->id)->update([
                        'business_id' => $cust->business_id,
                        'branch_id'   => $targetBranch?->id,
                    ]);
                } else {
                    $unassignedTempOrders++;
                }
            }
        }

        $unassignedDrafts = 0;
        if (Schema::hasTable('ai_order_drafts')) {
            $drafts = DB::table('ai_order_drafts')->whereNull('business_id')->get();
            foreach ($drafts as $draft) {
                $cust = null;
                if (!empty($draft->sender_phone)) {
                    $cust = DB::table('customers')->where('phone', $draft->sender_phone)->first();
                    if (!$cust && Schema::hasTable('customer_phones')) {
                        $phoneRow = DB::table('customer_phones')->where('phone', $draft->sender_phone)->first();
                        if ($phoneRow) {
                            $cust = DB::table('customers')->where('id', $phoneRow->customer_id)->first();
                        }
                    }
                }

                if ($cust && !empty($cust->business_id)) {
                    $targetBranch = DB::table('branches')
                        ->where('business_id', $cust->business_id)
                        ->where('is_main', 1)
                        ->first();

                    DB::table('ai_order_drafts')->where('id', $draft->id)->update([
                        'business_id' => $cust->business_id,
                        'branch_id'   => $targetBranch?->id,
                    ]);
                } else {
                    $unassignedDrafts++;
                }
            }
        }

        if ($unassignedTempOrders > 0 || $unassignedDrafts > 0) {
            Log::warning("[Migration] Backfill AI Orders: Terdapat {$unassignedTempOrders} temporary_orders dan {$unassignedDrafts} ai_order_drafts yang dibiarkan NULL karena tidak terkait customer manapun.");
        }

        // -------------------------------------------------------------
        // 3. CEK DUPLIKAT INVENTORY & TERAPKAN UNIQUE INDEX
        // -------------------------------------------------------------

        // A. CEK DUPLIKAT INVENTORY
        if (Schema::hasTable('inventory')) {
            $duplicates = DB::table('inventory')
                ->select('business_id', 'branch_id', 'product_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('branch_id')
                ->groupBy('business_id', 'branch_id', 'product_id')
                ->having('total', '>', 1)
                ->get();

            if ($duplicates->isNotEmpty()) {
                throw new \RuntimeException(
                    "Migrasi dihentikan: Ditemukan " . $duplicates->count() .
                    " duplikat inventory untuk kombinasi (business_id, branch_id, product_id). " .
                    "Data tidak dihapus secara otomatis. Harap selesaikan duplikasi data secara manual terlebih dahulu."
                );
            }

            if (!$this->hasIndex('inventory', 'inventory_business_branch_product_unique')) {
                Schema::table('inventory', function (Blueprint $table) {
                    $table->unique(['business_id', 'branch_id', 'product_id'], 'inventory_business_branch_product_unique');
                });
            }
        }

        // B. CASHIER OPENING BALANCES: Ganti unique lama (buat baru DULU, lalu drop lama)
        if (Schema::hasTable('cashier_opening_balances')) {
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

        // C. OPNAME SESSIONS: Tambah indeks branch lookup
        if (Schema::hasTable('opname_sessions')) {
            if (!$this->hasIndex('opname_sessions', 'opname_sessions_branch_lookup_idx')) {
                Schema::table('opname_sessions', function (Blueprint $table) {
                    $table->index(['business_id', 'branch_id', 'created_at'], 'opname_sessions_branch_lookup_idx');
                });
            }
        }

        // D. AI ORDER DRAFTS: Ubah unique sender_phone menjadi (branch_id, sender_phone)
        if (Schema::hasTable('ai_order_drafts')) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Rollback AI ORDER DRAFTS
        if (Schema::hasTable('ai_order_drafts')) {
            Schema::table('ai_order_drafts', function (Blueprint $table) {
                if ($this->hasIndex('ai_order_drafts', 'ai_order_drafts_branch_phone_unique')) {
                    $table->dropUnique('ai_order_drafts_branch_phone_unique');
                }
                if (!$this->hasIndex('ai_order_drafts', 'ai_order_drafts_sender_phone_unique')) {
                    $table->unique('sender_phone', 'ai_order_drafts_sender_phone_unique');
                }
                if (Schema::hasColumn('ai_order_drafts', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
                if (Schema::hasColumn('ai_order_drafts', 'business_id')) {
                    $table->dropForeign(['business_id']);
                    $table->dropColumn('business_id');
                }
            });
        }

        // 2. Rollback OPNAME SESSIONS
        if (Schema::hasTable('opname_sessions')) {
            Schema::table('opname_sessions', function (Blueprint $table) {
                if ($this->hasIndex('opname_sessions', 'opname_sessions_branch_lookup_idx')) {
                    $table->dropIndex('opname_sessions_branch_lookup_idx');
                }
                if (Schema::hasColumn('opname_sessions', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
        }

        // 3. Rollback CASHIER OPENING BALANCES
        if (Schema::hasTable('cashier_opening_balances')) {
            Schema::table('cashier_opening_balances', function (Blueprint $table) {
                if (!$this->hasIndex('cashier_opening_balances', 'cashier_daily_opening_unique')) {
                    $table->unique(['business_id', 'user_id', 'date'], 'cashier_daily_opening_unique');
                }
                if ($this->hasIndex('cashier_opening_balances', 'cashier_branch_daily_opening_unique')) {
                    $table->dropUnique('cashier_branch_daily_opening_unique');
                }
                if (Schema::hasColumn('cashier_opening_balances', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
        }

        // 4. Rollback INVENTORY
        if (Schema::hasTable('inventory')) {
            Schema::table('inventory', function (Blueprint $table) {
                if ($this->hasIndex('inventory', 'inventory_business_branch_product_unique')) {
                    $table->dropUnique('inventory_business_branch_product_unique');
                }
                if (Schema::hasColumn('inventory', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
        }

        // 5. Rollback TEMPORARY ORDERS
        if (Schema::hasTable('temporary_orders')) {
            Schema::table('temporary_orders', function (Blueprint $table) {
                if (Schema::hasColumn('temporary_orders', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
                if (Schema::hasColumn('temporary_orders', 'business_id')) {
                    $table->dropForeign(['business_id']);
                    $table->dropColumn('business_id');
                }
            });
        }

        // 6. Rollback USER LOGS & SESSIONS
        if (Schema::hasTable('user_sessions') && Schema::hasColumn('user_sessions', 'branch_id')) {
            Schema::table('user_sessions', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('user_activity_logs') && Schema::hasColumn('user_activity_logs', 'branch_id')) {
            Schema::table('user_activity_logs', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // 7. Rollback CASH FLOW
        if (Schema::hasTable('cash_flow') && Schema::hasColumn('cash_flow', 'branch_id')) {
            Schema::table('cash_flow', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // 8. Rollback STOCK MOVEMENTS
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                if (Schema::hasColumn('stock_movements', 'destination_branch_id')) {
                    $table->dropForeign(['destination_branch_id']);
                    $table->dropColumn('destination_branch_id');
                }
                if (Schema::hasColumn('stock_movements', 'branch_id')) {
                    $table->dropForeign(['branch_id']);
                    $table->dropColumn('branch_id');
                }
            });
        }

        // 9. Rollback TRANSACTIONS
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        // 10. Rollback OWNER EXPENSES
        if (Schema::hasTable('owner_expenses') && Schema::hasColumn('owner_expenses', 'business_id')) {
            Schema::table('owner_expenses', function (Blueprint $table) {
                $table->dropForeign(['business_id']);
                $table->dropColumn('business_id');
            });
        }
    }
};
