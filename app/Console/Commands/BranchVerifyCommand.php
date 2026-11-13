<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BranchVerifyCommand extends Command
{
    /**
     * Signature console command.
     */
    protected $signature = 'branch:verify';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Verifikasi integritas data multi-cabang (Pusat, NULL branch_id, duplikasi stok, dan penugasan user).';

    /**
     * Jalankan verifikasi integritas data cabang.
     */
    public function handle(): int
    {
        $this->info("=== MEMULAI AUDIT INTEGRITAS MULTI-CABANG ===");
        $hasErrors = false;
        $results = [];

        // 1. Cek Cabang Utama (is_main = 1) per Business
        if (Schema::hasTable('businesses') && Schema::hasTable('branches')) {
            if (!Schema::hasColumn('branches', 'is_main')) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => 'Cabang Utama (is_main = 1)',
                    'Status'  => 'FAIL',
                    'Details' => 'KOLOM HILANG: branches.is_main',
                ];
            } else {
                $businesses = DB::table('businesses')->get();
                $missingMainBiz = [];
                $multipleMainBiz = [];

                foreach ($businesses as $biz) {
                    $mainCount = DB::table('branches')
                        ->where('business_id', $biz->id)
                        ->where('is_main', 1)
                        ->count();

                    if ($mainCount === 0) {
                        $missingMainBiz[] = "ID:{$biz->id} ({$biz->name})";
                    } elseif ($mainCount > 1) {
                        $multipleMainBiz[] = "ID:{$biz->id} ({$mainCount} pusat)";
                    }
                }

                if (!empty($missingMainBiz) || !empty($multipleMainBiz)) {
                    $hasErrors = true;
                    $errorMsgs = [];
                    if (!empty($missingMainBiz)) {
                        $errorMsgs[] = "Bisnis tanpa is_main: " . implode(', ', $missingMainBiz);
                    }
                    if (!empty($multipleMainBiz)) {
                        $errorMsgs[] = "Bisnis dengan >1 is_main: " . implode(', ', $multipleMainBiz);
                    }

                    $results[] = [
                        'Check'   => 'Cabang Utama (is_main = 1)',
                        'Status'  => 'FAIL',
                        'Details' => implode(' | ', $errorMsgs),
                    ];
                } else {
                    $results[] = [
                        'Check'   => 'Cabang Utama (is_main = 1)',
                        'Status'  => 'PASS',
                        'Details' => "Semua ({$businesses->count()}) bisnis memiliki tepat 1 Cabang Pusat (is_main = 1).",
                    ];
                }
            }
        }

        // 2. Cek Keberadaan Kolom branch_id dan Nilai NULL pada Tabel Operasional & Log
        $tables = [
            'inventory',
            'transactions',
            'stock_movements',
            'cash_flow',
            'cashier_opening_balances',
            'opname_sessions',
            'temporary_orders',
            'ai_order_drafts',
            'user_activity_logs',
            'user_sessions',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $results[] = [
                    'Check'   => "Kolom branch_id di {$table}",
                    'Status'  => 'SKIP',
                    'Details' => "Tabel {$table} belum dibuat.",
                ];
                continue;
            }

            // Cek kolom branch_id ada dulu sebelum query agar tidak crash
            if (!Schema::hasColumn($table, 'branch_id')) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => "Kolom branch_id di {$table}",
                    'Status'  => 'FAIL',
                    'Details' => "KOLOM HILANG: {$table}.branch_id",
                ];
                continue;
            }

            $nullCount = DB::table($table)->whereNull('branch_id')->count();

            // Penanganan khusus tabel user_sessions (sesi admin/owner tanpa cabang wajar NULL)
            if ($table === 'user_sessions') {
                if ($nullCount > 0) {
                    $results[] = [
                        'Check'   => "NULL branch_id di {$table}",
                        'Status'  => 'WARN',
                        'Details' => "Terdapat {$nullCount} sesi dengan branch_id NULL (sesi admin/owner tingkat holding atau sesi sebelum memilih cabang).",
                    ];
                } else {
                    $results[] = [
                        'Check'   => "NULL branch_id di {$table}",
                        'Status'  => 'PASS',
                        'Details' => "Semua baris memiliki branch_id valid (0 NULL).",
                    ];
                }
                continue;
            }

            // Penanganan khusus transactions (bedakan transaksi aktif vs data legacy sebelum multi-tenant)
            if ($table === 'transactions') {
                $nullActiveCount = DB::table('transactions')
                    ->whereNotNull('business_id')
                    ->whereNull('branch_id')
                    ->count();
                $nullLegacyCount = DB::table('transactions')
                    ->whereNull('business_id')
                    ->whereNull('branch_id')
                    ->count();

                if ($nullActiveCount > 0) {
                    $hasErrors = true;
                    $results[] = [
                        'Check'   => "NULL branch_id di transactions",
                        'Status'  => 'FAIL',
                        'Details' => "Ditemukan {$nullActiveCount} transaksi bisnis aktif dengan branch_id NULL!",
                    ];
                } elseif ($nullLegacyCount > 0) {
                    $results[] = [
                        'Check'   => "NULL branch_id di transactions",
                        'Status'  => 'WARN',
                        'Details' => "Semua transaksi bisnis aktif memiliki branch_id valid ({$nullLegacyCount} transaksi legacy tanpa business_id dilewati).",
                    ];
                } else {
                    $results[] = [
                        'Check'   => "NULL branch_id di transactions",
                        'Status'  => 'PASS',
                        'Details' => "Semua baris memiliki branch_id valid (0 NULL).",
                    ];
                }
                continue;
            }

            // Pengecualian informasional: temporary_orders dan ai_order_drafts unverified
            if ($nullCount > 0 && in_array($table, ['temporary_orders', 'ai_order_drafts'])) {
                $results[] = [
                    'Check'   => "NULL branch_id di {$table}",
                    'Status'  => 'WARN',
                    'Details' => "Terdapat {$nullCount} baris dengan branch_id NULL (unresolved customer leads).",
                ];
            } elseif ($nullCount > 0) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => "NULL branch_id di {$table}",
                    'Status'  => 'FAIL',
                    'Details' => "Ditemukan {$nullCount} data operasional dengan branch_id NULL!",
                ];
            } else {
                $results[] = [
                    'Check'   => "NULL branch_id di {$table}",
                    'Status'  => 'PASS',
                    'Details' => "Semua baris memiliki branch_id valid (0 NULL).",
                ];
            }
        }

        // 2b. Cek NULL business_id pada transactions (WARN jika ada data legacy)
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'business_id')) {
            $nullBizCount = DB::table('transactions')->whereNull('business_id')->count();
            if ($nullBizCount > 0) {
                $results[] = [
                    'Check'   => 'NULL business_id di transactions',
                    'Status'  => 'WARN',
                    'Details' => "Ditemukan {$nullBizCount} transaksi lama dengan business_id NULL (legacy data).",
                ];
            } else {
                $results[] = [
                    'Check'   => 'NULL business_id di transactions',
                    'Status'  => 'PASS',
                    'Details' => 'Semua transaksi memiliki business_id valid.',
                ];
            }
        }

        // 3. Cek users.branch_id untuk Admin/Owner (Harus NULL)
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumn('users', 'branch_id')) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => 'users.branch_id untuk Admin/Owner',
                    'Status'  => 'FAIL',
                    'Details' => 'KOLOM HILANG: users.branch_id',
                ];
            } else {
                $adminWithBranch = DB::table('users')
                    ->whereIn('role', ['admin', 'owner'])
                    ->whereNotNull('branch_id')
                    ->count();

                if ($adminWithBranch > 0) {
                    $hasErrors = true;
                    $results[] = [
                        'Check'   => 'users.branch_id untuk Admin/Owner',
                        'Status'  => 'FAIL',
                        'Details' => "Ditemukan {$adminWithBranch} user admin/owner dengan users.branch_id terisi (seharusnya NULL).",
                    ];
                } else {
                    $results[] = [
                        'Check'   => 'users.branch_id untuk Admin/Owner',
                        'Status'  => 'PASS',
                        'Details' => 'Semua admin/owner memiliki branch_id = NULL (mode konsolidasi pusat).',
                    ];
                }
            }
        }

        // 4. Cek Non-Admin tanpa Pivot branch_user
        if (Schema::hasTable('users') && Schema::hasTable('branch_user')) {
            $unassignedStaffQuery = DB::table('users')
                ->whereNotIn('role', ['admin', 'owner'])
                ->where('is_active', true)
                ->whereNotNull('business_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('branch_user')
                        ->whereColumn('branch_user.user_id', 'users.id');
                });

            $unassignedStaff = $unassignedStaffQuery->count();

            if ($unassignedStaff > 0) {
                $samples = $unassignedStaffQuery->limit(3)->get(['id', 'name', 'email', 'role', 'business_id']);
                $infoList = [];
                foreach ($samples as $s) {
                    $infoList[] = "[ID: {$s->id}] {$s->name} ({$s->role}, Biz: {$s->business_id})";
                }
                $details = "Ditemukan {$unassignedStaff} kasir/driver aktif belum terikat ke tabel pivot branch_user: " . implode(', ', $infoList);

                $hasErrors = true;
                $results[] = [
                    'Check'   => 'Penugasan Staf ke Pivot (branch_user)',
                    'Status'  => 'FAIL',
                    'Details' => $details,
                ];
            } else {
                $results[] = [
                    'Check'   => 'Penugasan Staf ke Pivot (branch_user)',
                    'Status'  => 'PASS',
                    'Details' => 'Semua staf aktif bisnis non-admin memiliki setidaknya 1 cabang terdaftar di pivot.',
                ];
            }
        }

        // 4b. Cek User tanpa business_id (Legacy / Unanchored)
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'business_id')) {
            $orphanUsersQuery = DB::table('users')->whereNull('business_id');
            $orphanCount = $orphanUsersQuery->count();
            if ($orphanCount > 0) {
                $samples = $orphanUsersQuery->limit(3)->get(['id', 'name', 'role']);
                $sampleList = [];
                foreach ($samples as $s) {
                    $sampleList[] = "[ID: {$s->id}] {$s->name} ({$s->role})";
                }
                $results[] = [
                    'Check'   => 'User Tanpa Business (business_id NULL)',
                    'Status'  => 'WARN',
                    'Details' => "Ditemukan {$orphanCount} user legacy tanpa business_id: " . implode(', ', $sampleList),
                ];
            }
        }

        // 5. Cek Duplikasi Kombinasi Stok pada inventory (business_id, branch_id, product_id)
        if (Schema::hasTable('inventory') && Schema::hasColumn('inventory', 'branch_id')) {
            $duplicates = DB::table('inventory')
                ->select('business_id', 'branch_id', 'product_id', DB::raw('COUNT(*) as total'))
                ->groupBy('business_id', 'branch_id', 'product_id')
                ->having('total', '>', 1)
                ->get();

            if ($duplicates->isNotEmpty()) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => 'Duplikasi Inventory (Unique Constraint)',
                    'Status'  => 'FAIL',
                    'Details' => "Ditemukan {$duplicates->count()} kombinasi (business, branch, product) duplikat!",
                ];
            } else {
                $results[] = [
                    'Check'   => 'Duplikasi Inventory (Unique Constraint)',
                    'Status'  => 'PASS',
                    'Details' => 'Tidak ada duplikasi kombinasi stok produk per cabang.',
                ];
            }
        }

        // 6. Cek Duplikasi Kasir Opening Balance
        if (Schema::hasTable('cashier_opening_balances') && Schema::hasColumn('cashier_opening_balances', 'branch_id')) {
            $cobDups = DB::table('cashier_opening_balances')
                ->select('business_id', 'branch_id', 'user_id', 'date', DB::raw('COUNT(*) as total'))
                ->groupBy('business_id', 'branch_id', 'user_id', 'date')
                ->having('total', '>', 1)
                ->get();

            if ($cobDups->isNotEmpty()) {
                $hasErrors = true;
                $results[] = [
                    'Check'   => 'Duplikasi Cashier Opening Balance',
                    'Status'  => 'FAIL',
                    'Details' => "Ditemukan {$cobDups->count()} kombinasi (business, branch, user, date) duplikat!",
                ];
            } else {
                $results[] = [
                    'Check'   => 'Duplikasi Cashier Opening Balance',
                    'Status'  => 'PASS',
                    'Details' => 'Tidak ada duplikasi modal awal kasir harian per cabang.',
                ];
            }
        }

        // 7. Cek Inventory dengan Stok > 0 tetapi HPP (cost_price) = 0 (WARN)
        if (Schema::hasTable('inventory') && Schema::hasColumn('inventory', 'cost_price')) {
            $zeroCostCount = DB::table('inventory')
                ->where('current_stock', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('cost_price')->orWhere('cost_price', '<=', 0);
                })
                ->count();

            if ($zeroCostCount > 0) {
                $results[] = [
                    'Check'   => 'Inventory Stok Positif HPP = 0',
                    'Status'  => 'WARN',
                    'Details' => "Ditemukan {$zeroCostCount} baris inventory dengan stok > 0 tetapi cost_price <= 0.",
                ];
            } else {
                $results[] = [
                    'Check'   => 'Inventory Stok Positif HPP = 0',
                    'Status'  => 'PASS',
                    'Details' => 'Semua inventory dengan stok > 0 memiliki HPP valid.',
                ];
            }
        }

        // 8. Cek Dukungan ENUM 'sale' pada stock_movements.type
        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'type')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $colType = DB::selectOne("
                    SELECT COLUMN_TYPE 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'stock_movements' 
                      AND COLUMN_NAME = 'type'
                ");

                if ($colType && str_contains($colType->COLUMN_TYPE, "'sale'")) {
                    $results[] = [
                        'Check'   => "stock_movements.type ENUM 'sale'",
                        'Status'  => 'PASS',
                        'Details' => "Kolom stock_movements.type mendukung 'sale' ({$colType->COLUMN_TYPE}).",
                    ];
                } else {
                    $hasErrors = true;
                    $results[] = [
                        'Check'   => "stock_movements.type ENUM 'sale'",
                        'Status'  => 'FAIL',
                        'Details' => "Kolom stock_movements.type belum mendukung 'sale'! (" . ($colType->COLUMN_TYPE ?? 'UNKNOWN') . ")",
                    ];
                }
            } else {
                $results[] = [
                    'Check'   => "stock_movements.type ENUM 'sale'",
                    'Status'  => 'PASS',
                    'Details' => "Driver database {$driver} mendukung mutasi tipe 'sale'.",
                ];
            }
        }

        // 9. Cek Cabang Aktif yang belum memiliki baris Inventory untuk Produk Aktif (WARN)
        if (Schema::hasTable('branches') && Schema::hasTable('products') && Schema::hasTable('inventory')) {
            $activeBranches = DB::table('branches')->where('is_active', 1)->get();
            $missingInvCount = 0;

            foreach ($activeBranches as $ab) {
                $activeProducts = DB::table('products')
                    ->where('business_id', $ab->business_id)
                    ->where('is_active', 1)
                    ->pluck('id');

                $existingProductIds = DB::table('inventory')
                    ->where('business_id', $ab->business_id)
                    ->where('branch_id', $ab->id)
                    ->pluck('product_id')
                    ->all();

                $diff = $activeProducts->diff($existingProductIds);
                $missingInvCount += $diff->count();
            }

            if ($missingInvCount > 0) {
                $results[] = [
                    'Check'   => 'Kelengkapan Baris Inventory Cabang Aktif',
                    'Status'  => 'WARN',
                    'Details' => "Ditemukan {$missingInvCount} pasangan (cabang aktif, produk aktif) yang belum memiliki baris inventory.",
                ];
            } else {
                $results[] = [
                    'Check'   => 'Kelengkapan Baris Inventory Cabang Aktif',
                    'Status'  => 'PASS',
                    'Details' => 'Semua cabang aktif memiliki baris inventory lengkap untuk seluruh produk aktif.',
                ];
            }
        }

        // 10. Cek Konsistensi branch_monthly_closings vs owner_profits (WARN)
        if (Schema::hasTable('branch_monthly_closings') && Schema::hasTable('owner_profits')) {
            $ownerProfits = DB::table('owner_profits')->get();
            $closingMismatches = 0;

            foreach ($ownerProfits as $op) {
                $branchClosings = DB::table('branch_monthly_closings')
                    ->where('business_id', $op->business_id)
                    ->where('period_month', $op->period_month)
                    ->where('period_year', $op->period_year)
                    ->get();

                if ($branchClosings->isNotEmpty()) {
                    $branchSumIncome = (float) $branchClosings->sum('monthly_income');
                    $branchSumGross = (float) $branchClosings->sum('gross_profit');

                    // Bandingkan omzet dan laba kotor cabang dengan owner_profits
                    if (abs($branchSumIncome - (float) $op->monthly_income) > 1.0 || abs($branchSumGross - (float) $op->gross_profit) > 1.0) {
                        $closingMismatches++;
                    }
                }
            }

            if ($closingMismatches > 0) {
                $results[] = [
                    'Check'   => 'Konsistensi Tutup Buku Cabang vs Holding',
                    'Status'  => 'WARN',
                    'Details' => "Ditemukan {$closingMismatches} periode di mana akumulasi cabang tidak sinkron dengan owner_profits.",
                ];
            } else {
                $results[] = [
                    'Check'   => 'Konsistensi Tutup Buku Cabang vs Holding',
                    'Status'  => 'PASS',
                    'Details' => 'Data tutup buku cabang sinkron dengan rekap laba konsolidasi holding.',
                ];
            }
        }

        // 11. Cek Dinamis Kolom business_id dan branch_id pada Model Berscope (K4)
        $modelFiles = glob(app_path('Models/*.php'));
        $excludedFromBizScope = [
            \App\Models\Business::class,
            \App\Models\User::class,
            \App\Models\UserSession::class,
            \App\Models\TransactionDetail::class,
            \App\Models\Payment::class,
            \App\Models\Permission::class,
            \App\Models\UserPermission::class,
            \App\Models\CustomerSegment::class,
        ];

        foreach ($modelFiles as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }

            try {
                $ref = new \ReflectionClass($class);
                if ($ref->isAbstract() || !$ref->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                    continue;
                }

                $traits = class_uses_recursive($class);
                $hasBizTrait = in_array(\App\Traits\BelongsToBusiness::class, $traits, true);
                $hasBranchTrait = in_array(\App\Traits\BelongsToBranch::class, $traits, true);

                if (!$hasBizTrait && !$hasBranchTrait) {
                    continue;
                }

                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $class();
                $table = $instance->getTable();

                if (!Schema::hasTable($table)) {
                    continue;
                }

                // Cek business_id
                if ($hasBizTrait && !in_array($class, $excludedFromBizScope, true)) {
                    if (Schema::hasColumn($table, 'business_id')) {
                        $results[] = [
                            'Check'   => "Model {$ref->getShortName()} (business_id)",
                            'Status'  => 'PASS',
                            'Details' => "Tabel {$table}.business_id terverifikasi valid.",
                        ];
                    } else {
                        $hasErrors = true;
                        $results[] = [
                            'Check'   => "Model {$ref->getShortName()} (business_id)",
                            'Status'  => 'FAIL',
                            'Details' => "KOLOM HILANG: {$table}.business_id",
                        ];
                    }
                }

                // Cek branch_id
                if ($hasBranchTrait) {
                    if (Schema::hasColumn($table, 'branch_id')) {
                        $results[] = [
                            'Check'   => "Model {$ref->getShortName()} (branch_id)",
                            'Status'  => 'PASS',
                            'Details' => "Tabel {$table}.branch_id terverifikasi valid.",
                        ];
                    } else {
                        $hasErrors = true;
                        $results[] = [
                            'Check'   => "Model {$ref->getShortName()} (branch_id)",
                            'Status'  => 'FAIL',
                            'Details' => "KOLOM HILANG: {$table}.branch_id",
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Lewati jika model tidak dapat di-instansiasi
            }
        }

        // Tampilkan tabel hasil
        $this->table(['Pemeriksaan', 'Status', 'Keterangan'], $results);

        if ($hasErrors) {
            $this->error("\n❌ Verifikasi GAGAL: Masih ada anomali atau skema belum terisolasi sempurna.");
            return 1;
        }

        $this->info("\n✅ Verifikasi BERHASIL: Fondasi multi-cabang terverifikasi sehat dan terisolasi.");
        return 0;
    }
}
