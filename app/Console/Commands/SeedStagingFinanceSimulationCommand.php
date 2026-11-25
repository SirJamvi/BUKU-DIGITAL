<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Business;
use App\Models\Branch;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Inventory;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\StockMovement;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\CashierShiftClosing;
use App\Models\OwnerExpense;
use App\Support\BranchContext;

class SeedStagingFinanceSimulationCommand extends Command
{
    /**
     * Signature console command.
     */
    protected $signature = 'staging:finance-simulation
                            {--business-id= : ID bisnis target (default: 10 atau bisnis cabang aktif)}
                            {--branch-id= : ID cabang target (jika ingin spesifik 1 cabang saja)}
                            {--all-branches : Generate simulasi lengkap untuk 3 cabang sekaligus (PUSAT, AC-02, CB-C)}
                            {--cashier-email= : Email kasir target untuk single branch}
                            {--date= : Tanggal simulasi YYYY-MM-DD (default: hari ini Asia/Jakarta)}
                            {--clean : Hapus seluruh data bertanda [SIMULASI] tanpa membuat data baru}';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Generate data simulasi transaksi dummy lengkap seluruh skenario SSOT finance dan deposit lintas cabang untuk pengujian visual staging';

    /**
     * Eksekusi command.
     */
    public function handle(): int
    {
        // 1. ATURAN KEAMANAN WAJIB: Cegah jalan di production
        if (app()->environment('production')) {
            $this->error("DITOLAK: Command simulasi dummy hanya boleh dijalankan di staging/local!");
            return 1;
        }

        $appUrl = (string) config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
        $isSafeHost = Str::contains($host, ['staging', 'localhost', '127.0.0.1', 'spb.resdigaza.my.id', 'local', '.test']);
        if (!$isSafeHost && !app()->environment(['local', 'staging', 'testing', 'development'])) {
            $this->error("DITOLAK: Command simulasi dummy hanya boleh dijalankan di staging/local!");
            return 1;
        }

        // 2. Resolusi Bisnis Target
        $businessIdOption = $this->option('business-id');
        $businessId = $businessIdOption ? (int) $businessIdOption : 10;

        // Cek apakah bisnis valid
        $business = Business::withoutGlobalScopes()->find($businessId);
        if (!$business) {
            $branchFallback = Branch::withoutGlobalScopes()->where('is_active', true)->first();
            if ($branchFallback) {
                $businessId = $branchFallback->business_id;
            } else {
                $this->error("Tidak ditemukan record bisnis aktif di database.");
                return 1;
            }
        }

        // 3. Opsi Pembersihan Bersih (--clean)
        if ($this->option('clean')) {
            $this->info("Menghapus seluruh data bertanda [SIMULASI] pada bisnis #{$businessId}...");
            $deletedCount = $this->cleanSimulationData($businessId, null);
            $this->info("Pembersihan selesai! Sebanyak {$deletedCount} data simulasi berhasil dihapus.");
            return 0;
        }

        // 4. Resolusi Tanggal Simulasi
        $dateInput = $this->option('date');
        $date = $dateInput ? Carbon::parse($dateInput)->format('Y-m-d') : Carbon::today('Asia/Jakarta')->format('Y-m-d');
        $dateCompact = Carbon::parse($date)->format('Ymd');
        $pastDate = Carbon::parse($date)->subDays(3)->format('Y-m-d');

        // 5. Ambil Admin Bisnis
        $admin = User::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('role', 'admin')
                  ->orWhereJsonContains('secondary_roles', 'admin')
                  ->orWhere('role', 'superadmin');
            })
            ->first()
            ?? User::withoutGlobalScopes()->where('business_id', $businessId)->first()
            ?? User::withoutGlobalScopes()->first();

        if ($admin) {
            Auth::login($admin);
        }

        // 6. Ambil Seluruh Cabang Aktif
        $allBranchesQuery = Branch::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('is_main', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        if ($allBranchesQuery->isEmpty()) {
            $allBranchesQuery = Branch::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->orderBy('id', 'asc')
                ->get();
        }

        if ($allBranchesQuery->isEmpty()) {
            $this->error("Tidak ditemukan cabang untuk business_id #{$businessId}.");
            return 1;
        }

        // Identifikasi 3 Cabang Target (PUSAT, AC-02, CB-C)
        $branchPusat = $allBranchesQuery->first(function ($b) {
            return $b->is_main || Str::contains(strtoupper($b->code ?? ''), 'PUSAT') || Str::contains(strtolower($b->name), 'krw');
        }) ?? $allBranchesQuery->get(0);

        $branchAc02 = $allBranchesQuery->first(function ($b) use ($branchPusat) {
            return $b->id !== $branchPusat->id && (
                Str::contains(strtoupper($b->code ?? ''), 'AC-02') ||
                Str::contains(strtolower($b->name), 'timur')
            );
        }) ?? $allBranchesQuery->get(1) ?? $branchPusat;

        $branchCbc = $allBranchesQuery->first(function ($b) use ($branchPusat, $branchAc02) {
            return $b->id !== $branchPusat->id && $b->id !== $branchAc02->id && (
                Str::contains(strtoupper($b->code ?? ''), 'CB-C') ||
                Str::contains(strtolower($b->name), 'teluk')
            );
        }) ?? $allBranchesQuery->get(2) ?? $branchAc02;

        $isMultiBranchMode = $this->option('all-branches') || (!$this->option('branch-id') && $allBranchesQuery->count() >= 2);

        $this->info("==========================================================================");
        $this->info("   MEMULAI SEEDING SIMULASI FINANCE SSOT & DEPOSIT LINTAS CABANG");
        $this->info("==========================================================================");
        $this->info("Bisnis Target: #{$businessId}");
        $this->info("Tanggal      : {$date}");
        $this->info("Mode         : " . ($isMultiBranchMode ? "Multi-Cabang (3 Cabang Sekaligus: PUSAT, AC-02, CB-C)" : "Single-Cabang"));

        // 7. Eksekusi Seeding dalam DB::transaction() (Idempotent)
        DB::transaction(function () use (
            $businessId, $admin, $date, $dateCompact, $pastDate,
            $branchPusat, $branchAc02, $branchCbc, $isMultiBranchMode
        ) {
            // A. Bersihkan data simulasi tanggal target terlebih dahulu agar idempotent
            $this->cleanSimulationData($businessId, $date);

            // B. Master Data: Kategori Produk & 2 Produk [SIMULASI]
            $prodCategory = ProductCategory::withoutGlobalScopes()->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'name'        => 'Es Kristal',
                ],
                [
                    'description' => 'Kategori Es Kristal Utama',
                    'is_active'   => true,
                    'created_by'  => $admin->id,
                ]
            );

            // Produk 1: Es Kristal Cube 10kg (Jual Rp 20.000, HPP Rp 12.000)
            $prodCube = Product::withoutGlobalScopes()->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'sku'         => 'SIM-CUBE-10KG',
                ],
                [
                    'name'        => '[SIMULASI] Es Kristal Cube 10kg',
                    'category_id' => $prodCategory->id,
                    'unit'        => 'Ball',
                    'base_price'  => 20000,
                    'cost_price'  => 12000,
                    'barcode'     => 'SIM-BAR-CUBE',
                    'is_active'   => true,
                    'is_service'  => false,
                    'created_by'  => $admin->id,
                ]
            );
            $prodCube->update(['base_price' => 20000, 'cost_price' => 12000, 'name' => '[SIMULASI] Es Kristal Cube 10kg']);

            // Produk 2: Es Kristal Tube 5kg (Jual Rp 12.000, HPP Rp 7.000)
            $prodTube = Product::withoutGlobalScopes()->firstOrCreate(
                [
                    'business_id' => $businessId,
                    'sku'         => 'SIM-TUBE-5KG',
                ],
                [
                    'name'        => '[SIMULASI] Es Kristal Tube 5kg',
                    'category_id' => $prodCategory->id,
                    'unit'        => 'Ball',
                    'base_price'  => 12000,
                    'cost_price'  => 7000,
                    'barcode'     => 'SIM-BAR-TUBE',
                    'is_active'   => true,
                    'is_service'  => false,
                    'created_by'  => $admin->id,
                ]
            );
            $prodTube->update(['base_price' => 12000, 'cost_price' => 7000, 'name' => '[SIMULASI] Es Kristal Tube 5kg']);

            // Pastikan stok di setiap cabang
            $targetBranches = $isMultiBranchMode ? [$branchPusat, $branchAc02, $branchCbc] : [$branchPusat];
            foreach ($targetBranches as $b) {
                Inventory::withoutGlobalScopes()->updateOrCreate(
                    ['business_id' => $businessId, 'branch_id' => $b->id, 'product_id' => $prodCube->id],
                    ['current_stock' => 500, 'cost_price' => 12000, 'min_stock' => 5, 'critical_stock' => 2]
                );
                Inventory::withoutGlobalScopes()->updateOrCreate(
                    ['business_id' => $businessId, 'branch_id' => $b->id, 'product_id' => $prodTube->id],
                    ['current_stock' => 500, 'cost_price' => 7000, 'min_stock' => 5, 'critical_stock' => 2]
                );
            }

            // C. Master Data: Kategori Pengeluaran & Pemasukan
            $catOpex = ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Beban Operasional'],
                ['type' => 'expense', 'category_type' => 'Operasional', 'is_cogs' => false, 'is_active' => true, 'created_by' => $admin->id]
            );
            $catHpp = ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Pembelian Stok HPP'],
                ['type' => 'expense', 'category_type' => 'HPP', 'is_cogs' => true, 'is_active' => true, 'created_by' => $admin->id]
            );
            $catPiutang = ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Piutang Karyawan'],
                ['type' => 'expense', 'category_type' => 'Piutang Karyawan', 'is_cogs' => false, 'is_active' => true, 'created_by' => $admin->id]
            );
            $catNonUsaha = ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Non-Usaha (Setoran/Refund)'],
                ['type' => 'expense', 'category_type' => 'Non-usaha', 'is_cogs' => false, 'is_active' => true, 'created_by' => $admin->id]
            );
            $catIncome = ExpenseCategory::withoutGlobalScopes()->updateOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Penjualan Langsung'],
                ['type' => 'income', 'category_type' => 'Operasional', 'is_cogs' => false, 'is_active' => true, 'created_by' => $admin->id]
            );

            // D. Pelanggan Simulasi (Scope Bisnis Global - Bebas Transaksi di Cabang Mana Pun)
            $custA = Customer::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Customer A (Deposit Lintas Cabang)'],
                ['phone' => '08999990101', 'join_date' => $date, 'status' => 'active', 'deposit_balance' => 0, 'created_by' => $admin->id]
            );
            $custA->update(['deposit_balance' => 0]);

            $custB = Customer::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $businessId, 'name' => '[SIMULASI] Customer B (Deposit Full)'],
                ['phone' => '08999990102', 'join_date' => $date, 'status' => 'active', 'deposit_balance' => 0, 'created_by' => $admin->id]
            );
            $custB->update(['deposit_balance' => 0]);

            $cust1 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Warung Bu Siti'], ['phone' => '08999990001', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust2 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Kafe Senja'], ['phone' => '08999990002', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust3 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Kedai Es Kopi'], ['phone' => '08999990003', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust4 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Resto Bebek'], ['phone' => '08999990004', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust5 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Warung Maju'], ['phone' => '08999990005', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust6 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Toko Berkah'], ['phone' => '08999990006', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);
            $cust7 = Customer::withoutGlobalScopes()->firstOrCreate(['business_id' => $businessId, 'name' => '[SIMULASI] Pelanggan Batal'], ['phone' => '08999990007', 'join_date' => $date, 'status' => 'active', 'created_by' => $admin->id]);

            // =========================================================================
            // EKSEKUSI SKENARIO CABANG 1: ADIN CRYSTAL PUSAT
            // =========================================================================
            $this->seedPusatBranch(
                $businessId, $branchPusat, $admin, $date, $dateCompact,
                $prodCube, $prodTube, $catIncome, $catOpex, $catNonUsaha,
                $custA, $cust1, $cust2, $cust4, $cust5
            );

            // =========================================================================
            // EKSEKUSI SKENARIO CABANG 2: ADIN CRYSTAL KARAWANG TIMUR (AC-02)
            // =========================================================================
            $this->seedKarawangTimurBranch(
                $businessId, $branchAc02, $admin, $date, $dateCompact, $pastDate,
                $prodCube, $prodTube, $catIncome, $catOpex, $catHpp, $catPiutang, $catNonUsaha,
                $custA, $cust1, $cust3, $cust4, $cust5, $cust6, $cust7
            );

            // =========================================================================
            // EKSEKUSI SKENARIO CABANG 3: ADIN CRYSTAL TELUKJAMBE (CB-C)
            // =========================================================================
            $this->seedTelukJambeBranch(
                $businessId, $branchCbc, $admin, $date, $dateCompact,
                $prodCube, $prodTube, $catIncome, $catOpex, $catNonUsaha,
                $custB, $cust1, $cust2
            );

            // =========================================================================
            // BEBAN HOLDING OWNER (Rp 100.000 di tabel owner_expenses)
            // =========================================================================
            OwnerExpense::withoutGlobalScopes()->create([
                'business_id'    => $businessId,
                'title'          => '[SIMULASI] Biaya Hosting & Sewa Kantor Pusat',
                'amount'         => 100000,
                'date'           => $date,
                'type'           => 'business',
                'status'         => 'approved',
                'payment_method' => 'transfer',
                'category'       => 'operational',
                'description'    => '[SIMULASI] Biaya Hosting Cloud & Domain Holding',
                'created_by'     => $admin->id,
            ]);
        });

        // 8. TAMPILKAN LAPORAN KONSOL LENGKAP
        $this->info("\n==========================================================================");
        $this->info(" ✔ SEEDING SIMULASI 3 CABANG & DEPOSIT LINTAS CABANG SELESAI");
        $this->info("==========================================================================");

        $this->table(
            ['Cabang', 'Kasir Bertugas', 'Driver Bertugas', 'Modal Awal', 'Kas Masuk Laci', 'Kas Keluar Laci', 'Expected Laci', 'Fisik Laci', 'Variance', 'Status Closing'],
            [
                ['PUSAT (Adin Crystal Krw)', $this->resolveCashier($businessId, $branchPusat->id)->name, $this->resolveDriver($businessId, $branchPusat->id)->name, 'Rp 300.000', 'Rp 710.000 (Inc. Dep 30k)', 'Rp 545.000', 'Rp 465.000', 'Rp 465.000', 'Rp 0', 'PAS (Approved)'],
                ['AC-02 (Karawang Timur)', $this->resolveCashier($businessId, $branchAc02->id)->name, $this->resolveDriver($businessId, $branchAc02->id)->name, 'Rp 200.000', 'Rp 490.000', 'Rp 405.000', 'Rp 285.000', 'Rp 280.000', '-Rp 5.000', 'SHORT (Review)'],
                ['CB-C (TelukJambe)', $this->resolveCashier($businessId, $branchCbc->id)->name, $this->resolveDriver($businessId, $branchCbc->id)->name, 'Rp 150.000', 'Rp 290.000 (Inc. Dep 30k)', 'Rp 175.000', 'Rp 265.000', 'Rp 267.000', '+Rp 2.000', 'OVER (Approved)'],
            ]
        );

        $this->table(
            ['Simulasi Pelanggan Deposit', 'Aktivitas Lintas Cabang', 'Saldo Akhir Deposit'],
            [
                ['Customer A (Deposit Lintas Cabang)', 'Setor deposit +Rp 30k di PUSAT -> Belanja di Karawang Timur (AC-02) potong deposit -Rp 30k & sisa Rp 10k tunai', 'Rp 0 (Habis terpakai)'],
                ['Customer B (Deposit Full)', 'Setor deposit +Rp 30k di TelukJambe -> Belanja walk-in lunas 100% deposit -Rp 20k -> Sedang diantar live trip SIM-TRIP-LIVE-01', 'Rp 10.000 (Sisa saldo)'],
            ]
        );

        $this->info("Tips Pengujian Live Dashboard:");
        $this->line("1. Login sebagai Kasir TelukJambe (CB-C) -> Buka Dashboard Kasir -> Anda akan melihat Container Pengiriman Driver Aktif [SIM-TRIP-LIVE-01] siap diuji (klik tombol + Deposit / Pelunasan)!");
        $this->line("2. Untuk membersihkan data simulasi ini nantinya: php artisan staging:finance-simulation --clean\n");

        return 0;
    }

    /**
     * Skenario Cabang 1: Adin Crystal PUSAT (Variance PAS Rp 0)
     */
    protected function seedPusatBranch(
        int $businessId, Branch $branch, User $admin, string $date, string $dateCompact,
        Product $prodCube, Product $prodTube, ExpenseCategory $catIncome, ExpenseCategory $catOpex, ExpenseCategory $catNonUsaha,
        Customer $custA, Customer $cust1, Customer $cust2, Customer $cust4, Customer $cust5
    ): void {
        $cashier = $this->resolveCashier($businessId, $branch->id);
        $driver = $this->resolveDriver($businessId, $branch->id);

        // 1. Modal Awal Laci (Rp 300.000)
        CashierOpeningBalance::withoutGlobalScopes()->create([
            'business_id' => $businessId,
            'branch_id'   => $branch->id,
            'user_id'     => $cashier->id,
            'date'        => $date,
            'amount'      => 300000,
            'notes'       => '[SIMULASI] Modal Awal Shift PUSAT',
        ]);

        // 2. T1: Customer A Delivery Driver setor Rp 50.000 (Hutang Rp 20.000 + Deposit Rp 30.000)
        $tx1 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $custA->id,
            'driver_id'                 => $driver->id,
            'claimed_by_driver_id'       => $driver->id,
            'delivery_trip_code'        => 'SIM-TRIP-PUSAT-01',
            'type'                      => 'sale',
            'total_amount'              => 20000,
            'deposit_used'              => 0,
            'net_payable_amount'        => 20000,
            'payment_method'            => 'cash',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'cash',
            'settled_at'                => $date . ' 09:30:00',
            'payment_status'            => 'paid',
            'delivery_status'           => 'delivered',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 08:30:00',
            'delivered_at'              => $date . ' 09:15:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Delivery Customer A (Pelunasan Rp 20k + Deposit Rp 30k)',
        ]);

        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx1->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 1,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 20000,
        ]);

        // Cash flow pelunasan kasbon Rp 20.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 20000,
            'description'    => '[SIMULASI] Pelunasan Kasbon Delivery (Customer A)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx1->id,
            'created_by'     => $cashier->id,
        ]);

        // Cash flow deposit tunai Rp 30.000 (Non-P&L titipan laci)
        $cfDep1 = CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'customer_deposit',
            'category_id'    => $catNonUsaha->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 30000,
            'description'    => '[SIMULASI] Penerimaan Deposit Pelanggan Tunai (Customer A)',
            'date'           => $date,
            'reference_type' => 'customer_deposit',
            'reference_id'   => (string) $custA->id,
            'created_by'     => $cashier->id,
        ]);

        CustomerDepositLog::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'customer_id'    => $custA->id,
            'transaction_id' => $tx1->id,
            'cash_flow_id'   => $cfDep1->id,
            'type'           => 'credit',
            'amount'         => 30000,
            'balance_before' => 0,
            'balance_after'  => 30000,
            'payment_method' => 'cash',
            'notes'          => '[SIMULASI] Setor Lebih Driver Pengiriman PUSAT (Deposit Baru)',
            'created_by'     => $cashier->id,
        ]);
        $custA->update(['deposit_balance' => 30000]);

        // 3. T2: Walk-in Tunai Rp 300.000 (15 Cube @ 20k, HPP 180k)
        $tx2 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust1->id,
            'type'                   => 'sale',
            'total_amount'           => 300000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 300000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 10:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan Tunai Meja PUSAT (T2)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx2->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 15,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 300000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 300000,
            'description'    => '[SIMULASI] Penjualan Tunai Meja (T2)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx2->id,
            'created_by'     => $cashier->id,
        ]);

        // 4. T3: Walk-in Transfer Bank Rp 400.000 (20 Cube @ 20k, HPP 240k)
        $tx3 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust2->id,
            'type'                   => 'sale',
            'total_amount'           => 400000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 400000,
            'payment_method'         => 'transfer-bank',
            'initial_payment_method' => 'transfer-bank',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 11:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan Transfer Bank PUSAT (T3)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx3->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 20,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 400000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'transfer-bank',
            'funding_source' => 'owner',
            'affects_drawer' => 0,
            'amount'         => 400000,
            'description'    => '[SIMULASI] Penjualan Transfer Bank (T3)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx3->id,
            'created_by'     => $cashier->id,
        ]);

        // 5. T4: Driver Trip Cash Rp 360.000 (30 Tube @ 12k, HPP 210k)
        $tx4 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $cust4->id,
            'driver_id'                 => $driver->id,
            'claimed_by_driver_id'       => $driver->id,
            'delivery_trip_code'        => 'SIM-TRIP-PUSAT-02',
            'type'                      => 'sale',
            'total_amount'              => 360000,
            'deposit_used'              => 0,
            'net_payable_amount'        => 360000,
            'payment_method'            => 'cash',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'cash',
            'settled_at'                => $date . ' 13:00:00',
            'payment_status'            => 'paid',
            'delivery_status'           => 'delivered',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 11:30:00',
            'delivered_at'              => $date . ' 12:45:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Delivery Kasbon Setor Tunai PUSAT (T4)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx4->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 30,
            'cost_price'     => 7000,
            'unit_price'     => 12000,
            'total_price'    => 360000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 360000,
            'description'    => '[SIMULASI] Pelunasan Kasbon Tunai (Resto Bebek) PUSAT (T4)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx4->id,
            'created_by'     => $cashier->id,
        ]);

        // 6. T5: Kasbon Pending Rp 200.000 (10 Cube @ 20k, HPP 120k)
        $tx5 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust5->id,
            'type'                   => 'sale',
            'total_amount'           => 200000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 200000,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 14:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Kasbon Baru Belum Lunas PUSAT (T5)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx5->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 10,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 200000,
        ]);

        // 7. Pengeluaran Laci PUSAT (OpEx Rp 45.000)
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'expense',
            'flow_type'      => 'expense',
            'category_id'    => $catOpex->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 45000,
            'description'    => '[SIMULASI] Bensin & Konsumsi PUSAT',
            'date'           => $date,
            'reference_type' => 'manual',
            'reference_id'   => 'SIM-EXP-PUSAT-01',
            'created_by'     => $cashier->id,
        ]);

        // 8. Handover Setoran ke Owner Rp 500.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'expense',
            'flow_type'      => 'cash_handover',
            'category_id'    => $catNonUsaha->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 500000,
            'description'    => '[SIMULASI] Setor Uang Laci PUSAT ke Owner',
            'date'           => $date,
            'reference_type' => 'handover',
            'reference_id'   => 'SIM-HANDOVER-PUSAT-' . $dateCompact,
            'created_by'     => $cashier->id,
        ]);

        // 9. Sync Gaji Eksternal Rp 500.000 (funding_source = external)
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'expense',
            'flow_type'      => 'salary_external',
            'category_id'    => $catOpex->id,
            'is_cogs'        => false,
            'payment_method' => 'transfer',
            'funding_source' => 'external',
            'affects_drawer' => 0,
            'amount'         => 500000,
            'description'    => '[SIMULASI] Sync Gaji Karyawan PUSAT',
            'date'           => $date,
            'reference_type' => 'attendance_salary',
            'reference_id'   => 'ATT-SIM-PUSAT-' . $dateCompact,
            'created_by'     => $admin->id,
        ]);

        // 10. Tutup Shift Kasir PUSAT (Expected: 300k + 710k - 545k = 465k, Actual: 465k -> Variance Rp 0 PAS)
        $closingData = [
            'business_id'                => $businessId,
            'branch_id'                  => $branch->id,
            'user_id'                    => $cashier->id,
            'date'                       => $date,
            'shift_code'                 => 'SHIFT-PUSAT',
            'opening_cash'               => 300000,
            'cash_sales'                 => 300000,
            'cash_debt_collection'       => 380000,
            'cash_expenses'              => 45000,
            'cash_handover_amount'       => 500000,
            'cash_refunds'               => 0,
            'expected_drawer_cash'       => 465000,
            'actual_cash_counted'        => 465000,
            'variance'                   => 0,
            'ending_cash_for_next_shift' => 465000,
            'notes'                      => '[SIMULASI] Tutup Shift PUSAT (Variance PAS Rp 0)',
            'closed_at'                  => $date . ' 22:00:00',
            'closed_by'                  => $cashier->id,
        ];
        if (Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
            $closingData['external_reference'] = 'ACS-SUB-SIM-PUSAT-' . $dateCompact;
        }
        CashierShiftClosing::withoutGlobalScopes()->create($closingData);
    }

    /**
     * Skenario Cabang 2: Adin Crystal Karawang Timur / AC-02 (Variance SHORT -Rp 5.000)
     */
    protected function seedKarawangTimurBranch(
        int $businessId, Branch $branch, User $admin, string $date, string $dateCompact, string $pastDate,
        Product $prodCube, Product $prodTube, ExpenseCategory $catIncome, ExpenseCategory $catOpex,
        ExpenseCategory $catHpp, ExpenseCategory $catPiutang, ExpenseCategory $catNonUsaha,
        Customer $custA, Customer $cust1, Customer $cust3, Customer $cust4, Customer $cust5, Customer $cust6, Customer $cust7
    ): void {
        $cashier = $this->resolveCashier($businessId, $branch->id);
        $driver = $this->resolveDriver($businessId, $branch->id);

        // 1. Modal Awal Laci (Rp 200.000)
        CashierOpeningBalance::withoutGlobalScopes()->create([
            'business_id' => $businessId,
            'branch_id'   => $branch->id,
            'user_id'     => $cashier->id,
            'date'        => $date,
            'amount'      => 200000,
            'notes'       => '[SIMULASI] Modal Awal Shift Karawang Timur',
        ]);

        // 2. T1: CROSS-BRANCH DEPOSIT SPLIT (Customer A belanja di Karawang Timur menggunakan deposit PUSAT Rp 30k)
        // Belanja 2 Cube @ 20k = Total Rp 40.000 (HPP 24k)
        // Potong Deposit Rp 30.000 -> Sisa Tagihan Rp 10.000 disetor tunai via driver
        $tx1 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $custA->id,
            'driver_id'                 => $driver->id,
            'claimed_by_driver_id'       => $driver->id,
            'delivery_trip_code'        => 'SIM-TRIP-AC02-01',
            'type'                      => 'sale',
            'total_amount'              => 40000,
            'deposit_used'              => 30000,
            'net_payable_amount'        => 10000,
            'payment_method'            => 'cash',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'cash',
            'settled_at'                => $date . ' 10:30:00',
            'payment_status'            => 'paid',
            'delivery_status'           => 'delivered',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 09:30:00',
            'delivered_at'              => $date . ' 10:15:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Cross-Branch Deposit Split Customer A (Total 40k, Potong Deposit PUSAT 30k, Sisa Kas 10k)',
        ]);

        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx1->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 2,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 40000,
        ]);

        // Ledger Audit Pemotongan Deposit (-Rp 30.000)
        CustomerDepositLog::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'customer_id'    => $custA->id,
            'transaction_id' => $tx1->id,
            'type'           => 'debit',
            'amount'         => 30000,
            'balance_before' => 30000,
            'balance_after'  => 0,
            'payment_method' => 'deposit',
            'notes'          => '[SIMULASI] Pemotongan Deposit Lintas Cabang di Karawang Timur (AC-02)',
            'created_by'     => $cashier->id,
        ]);
        $custA->update(['deposit_balance' => 0]);

        // Cash flow HANYA sebesar sisa kas riil (net_payable_amount = Rp 10.000)
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 10000,
            'description'    => '[SIMULASI] Pelunasan Sisa Kasbon Delivery (Customer A) Net Payable',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx1->id,
            'created_by'     => $cashier->id,
        ]);

        // 3. T2: Walk-in Tunai Rp 200.000 (10 Cube @ 20k, HPP 120k)
        $tx2 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust1->id,
            'type'                   => 'sale',
            'total_amount'           => 200000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 200000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 10:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan Tunai Meja Karawang Timur (T2)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx2->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 10,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 200000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 200000,
            'description'    => '[SIMULASI] Penjualan Tunai Meja (T2)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx2->id,
            'created_by'     => $cashier->id,
        ]);

        // 4. T3: Walk-in DANA Rp 120.000 (10 Tube @ 12k, HPP 70k)
        $tx3 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust3->id,
            'type'                   => 'sale',
            'total_amount'           => 120000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 120000,
            'payment_method'         => 'dana',
            'initial_payment_method' => 'dana',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 10:45:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan DANA Karawang Timur (T3)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx3->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 10,
            'cost_price'     => 7000,
            'unit_price'     => 12000,
            'total_price'    => 120000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'dana',
            'funding_source' => 'owner',
            'affects_drawer' => 0,
            'amount'         => 120000,
            'description'    => '[SIMULASI] Penjualan DANA (T3)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx3->id,
            'created_by'     => $cashier->id,
        ]);

        // 5. T4: Driver Trip Cash Rp 240.000 (20 Tube @ 12k, HPP 140k)
        $tx4 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $cust4->id,
            'driver_id'                 => $driver->id,
            'claimed_by_driver_id'       => $driver->id,
            'delivery_trip_code'        => 'SIM-TRIP-AC02-02',
            'type'                      => 'sale',
            'total_amount'              => 240000,
            'deposit_used'              => 0,
            'net_payable_amount'        => 240000,
            'payment_method'            => 'cash',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'cash',
            'settled_at'                => $date . ' 13:00:00',
            'payment_status'            => 'paid',
            'delivery_status'           => 'delivered',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 11:00:00',
            'delivered_at'              => $date . ' 12:30:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Delivery Kasbon Setor Tunai Karawang Timur (T4)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx4->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 20,
            'cost_price'     => 7000,
            'unit_price'     => 12000,
            'total_price'    => 240000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 240000,
            'description'    => '[SIMULASI] Pelunasan Kasbon Tunai Karawang Timur (T4)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx4->id,
            'created_by'     => $cashier->id,
        ]);

        // 6. T5: Kasbon Baru Pending Rp 120.000 (10 Tube @ 12k, HPP 70k)
        $tx5 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust5->id,
            'type'                   => 'sale',
            'total_amount'           => 120000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 120000,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 11:30:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Kasbon Baru Belum Lunas Karawang Timur (T5)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx5->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 10,
            'cost_price'     => 7000,
            'unit_price'     => 12000,
            'total_price'    => 120000,
        ]);

        // 7. T6: Pelunasan Kasbon Lama via Transfer Bank Rp 150.000 (HPP 90k)
        $tx6 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $cust6->id,
            'type'                      => 'sale',
            'total_amount'              => 150000,
            'deposit_used'              => 0,
            'net_payable_amount'        => 150000,
            'payment_method'            => 'transfer-bank',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'transfer-bank',
            'settled_at'                => $date . ' 14:00:00',
            'payment_status'            => 'paid',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $pastDate . ' 14:00:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Nota Kasbon Lama Lunas Hari Ini Karawang Timur (T6)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx6->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 5,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 100000,
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx6->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 5,
            'cost_price'     => 6000,
            'unit_price'     => 10000,
            'total_price'    => 50000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'transfer-bank',
            'funding_source' => 'owner',
            'affects_drawer' => 0,
            'amount'         => 150000,
            'description'    => '[SIMULASI] Pelunasan Kasbon Lama via Transfer (Toko Berkah) (T6)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx6->id,
            'created_by'     => $cashier->id,
        ]);

        // 8. T7: Same-day Soft Void Tunai Meja Rp 40.000 (2 Cube @ 20k, HPP 24k)
        $tx7 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust7->id,
            'type'                   => 'sale',
            'total_amount'           => 40000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 40000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'cancelled',
            'void_reason'            => '[SIMULASI] Salah input jumlah ball',
            'voided_by'              => $admin->id,
            'voided_at'              => $date . ' 10:30:00',
            'transaction_date'       => $date . ' 09:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Same-Day Soft Void Tunai Meja Karawang Timur (T7)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx7->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 2,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 40000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 40000,
            'description'    => '[SIMULASI] Penjualan Tunai Meja (Asal Void T7)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx7->id,
            'created_by'     => $cashier->id,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'expense',
            'flow_type'      => 'cash_refund',
            'category_id'    => $catNonUsaha->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 40000,
            'description'    => '[SIMULASI] Refund / Void Nota #' . $tx7->id . ' (T7)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx7->id,
            'created_by'     => $cashier->id,
        ]);

        // 9. Beban Operasional Laci (Bensin 20k + Lakban 15k = 35k)
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'expense',
            'category_id' => $catOpex->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 20000, 'description' => '[SIMULASI] Bensin Motor Kirim', 'date' => $date,
            'reference_type' => 'manual', 'reference_id' => 'SIM-EXP-AC02-01', 'created_by' => $cashier->id,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'expense',
            'category_id' => $catOpex->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 15000, 'description' => '[SIMULASI] Lakban & Plastik', 'date' => $date,
            'reference_type' => 'manual', 'reference_id' => 'SIM-EXP-AC02-02', 'created_by' => $cashier->id,
        ]);

        // 10. Pembelian Stok Luar (HPP) Rp 80.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'stock_purchase',
            'category_id' => $catHpp->id, 'is_cogs' => true, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 80000, 'description' => '[SIMULASI] Beli Es Luar Darurat', 'date' => $date,
            'reference_type' => 'manual', 'reference_id' => 'SIM-STOCK-AC02-01', 'created_by' => $cashier->id,
        ]);

        // 11. Piutang Karyawan Rp 50.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'expense',
            'category_id' => $catPiutang->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 50000, 'description' => '[SIMULASI] Kasbon Pribadi Driver', 'date' => $date,
            'reference_type' => 'manual', 'reference_id' => 'SIM-LOAN-AC02-01', 'created_by' => $cashier->id,
        ]);

        // 12. Handover Setoran ke Owner Rp 200.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'cash_handover',
            'category_id' => $catNonUsaha->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 200000, 'description' => '[SIMULASI] Setor Uang Laci Karawang Timur ke Owner', 'date' => $date,
            'reference_type' => 'handover', 'reference_id' => 'SIM-HANDOVER-AC02-' . $dateCompact, 'created_by' => $cashier->id,
        ]);

        // 13. Sync Gaji Eksternal Rp 500.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'salary_external',
            'category_id' => $catOpex->id, 'is_cogs' => false, 'payment_method' => 'transfer', 'funding_source' => 'external',
            'affects_drawer' => 0, 'amount' => 500000, 'description' => '[SIMULASI] Sync Gaji Karyawan Karawang Timur', 'date' => $date,
            'reference_type' => 'attendance_salary', 'reference_id' => 'ATT-SIM-AC02-' . $dateCompact, 'created_by' => $admin->id,
        ]);

        // 14. Tutup Shift Kasir Karawang Timur (Expected: 200k + 490k - 405k = 285k, Actual: 280k -> Variance -Rp 5.000 SHORT)
        $closingData = [
            'business_id'                => $businessId,
            'branch_id'                  => $branch->id,
            'user_id'                    => $cashier->id,
            'date'                       => $date,
            'shift_code'                 => 'SHIFT-AC02',
            'opening_cash'               => 200000,
            'cash_sales'                 => 240000,
            'cash_debt_collection'       => 250000,
            'cash_expenses'              => 165000,
            'cash_handover_amount'       => 200000,
            'cash_refunds'               => 40000,
            'expected_drawer_cash'       => 285000,
            'actual_cash_counted'        => 280000,
            'variance'                   => -5000,
            'ending_cash_for_next_shift' => 280000,
            'notes'                      => '[SIMULASI] Tutup Shift Karawang Timur (Selisih Kurang Rp 5.000)',
            'closed_at'                  => $date . ' 22:00:00',
            'closed_by'                  => $cashier->id,
        ];
        if (Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
            $closingData['external_reference'] = 'ACS-SUB-SIM-AC02-' . $dateCompact;
        }
        CashierShiftClosing::withoutGlobalScopes()->create($closingData);
    }

    /**
     * Skenario Cabang 3: Adin Crystal TelukJambe / CB-C (Variance OVER +Rp 2.000 & Active Delivering Live Trip)
     */
    protected function seedTelukJambeBranch(
        int $businessId, Branch $branch, User $admin, string $date, string $dateCompact,
        Product $prodCube, Product $prodTube, ExpenseCategory $catIncome, ExpenseCategory $catOpex, ExpenseCategory $catNonUsaha,
        Customer $custB, Customer $cust1, Customer $cust2
    ): void {
        $cashier = $this->resolveCashier($businessId, $branch->id);
        $driver = $this->resolveDriver($businessId, $branch->id);

        // 1. Modal Awal Laci (Rp 150.000)
        CashierOpeningBalance::withoutGlobalScopes()->create([
            'business_id' => $businessId,
            'branch_id'   => $branch->id,
            'user_id'     => $cashier->id,
            'date'        => $date,
            'amount'      => 150000,
            'notes'       => '[SIMULASI] Modal Awal Shift TelukJambe',
        ]);

        // 2. T1: Customer B Delivery Driver setor Rp 50.000 (Hutang Rp 20.000 + Deposit Rp 30.000)
        $tx1 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $custB->id,
            'driver_id'                 => $driver->id,
            'claimed_by_driver_id'       => $driver->id,
            'delivery_trip_code'        => 'SIM-TRIP-CBC-01',
            'type'                      => 'sale',
            'total_amount'              => 20000,
            'deposit_used'              => 0,
            'net_payable_amount'        => 20000,
            'payment_method'            => 'cash',
            'initial_payment_method'    => 'kasbon',
            'settlement_payment_method' => 'cash',
            'settled_at'                => $date . ' 09:30:00',
            'payment_status'            => 'paid',
            'delivery_status'           => 'delivered',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 08:30:00',
            'delivered_at'              => $date . ' 09:15:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Delivery Customer B (Pelunasan Rp 20k + Deposit Rp 30k)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx1->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 1,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 20000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 20000,
            'description'    => '[SIMULASI] Pelunasan Kasbon Delivery (Customer B)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx1->id,
            'created_by'     => $cashier->id,
        ]);
        $cfDepB = CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'customer_deposit',
            'category_id'    => $catNonUsaha->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 30000,
            'description'    => '[SIMULASI] Penerimaan Deposit Pelanggan Tunai (Customer B)',
            'date'           => $date,
            'reference_type' => 'customer_deposit',
            'reference_id'   => (string) $custB->id,
            'created_by'     => $cashier->id,
        ]);
        CustomerDepositLog::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'customer_id'    => $custB->id,
            'transaction_id' => $tx1->id,
            'cash_flow_id'   => $cfDepB->id,
            'type'           => 'credit',
            'amount'         => 30000,
            'balance_before' => 0,
            'balance_after'  => 30000,
            'payment_method' => 'cash',
            'notes'          => '[SIMULASI] Setor Lebih Driver Pengiriman TelukJambe (Deposit Baru)',
            'created_by'     => $cashier->id,
        ]);
        $custB->update(['deposit_balance' => 30000]);

        // 3. T2: Customer B Belanja Walk-in Lunas 100% dari Deposit (1 Cube @ 20k = 20k, Potong Deposit 20k -> Sisa Saldo 10k)
        $tx2 = Transaction::withoutGlobalScopes()->create([
            'business_id'               => $businessId,
            'branch_id'                 => $branch->id,
            'customer_id'               => $custB->id,
            'type'                      => 'sale',
            'total_amount'              => 20000,
            'deposit_used'              => 20000,
            'net_payable_amount'        => 0,
            'payment_method'            => 'deposit',
            'initial_payment_method'    => 'deposit',
            'settlement_payment_method' => 'deposit',
            'settled_at'                => $date . ' 10:15:00',
            'payment_status'            => 'paid',
            'order_status'              => 'completed',
            'status'                    => 'completed',
            'transaction_date'          => $date . ' 10:15:00',
            'created_by'                => $cashier->id,
            'notes'                     => '[SIMULASI] Transaksi Walk-in Lunas 100% dari Deposit (Customer B)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx2->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 1,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 20000,
        ]);
        CustomerDepositLog::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'customer_id'    => $custB->id,
            'transaction_id' => $tx2->id,
            'type'           => 'debit',
            'amount'         => 20000,
            'balance_before' => 30000,
            'balance_after'  => 10000,
            'payment_method' => 'deposit',
            'notes'          => '[SIMULASI] Pembayaran Transaksi POS 100% Saldo Deposit',
            'created_by'     => $cashier->id,
        ]);
        $custB->update(['deposit_balance' => 10000]);
        // TIDAK MEMBUAT CASH FLOW KARENA LUNAS DARI DEPOSIT (Rp 0 ke laci)

        // 4. T3: Walk-in Tunai Rp 240.000 (12 Cube @ 20k, HPP 144k)
        $tx3 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust1->id,
            'type'                   => 'sale',
            'total_amount'           => 240000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 240000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 11:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan Tunai Meja TelukJambe (T3)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx3->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 12,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 240000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'amount'         => 240000,
            'description'    => '[SIMULASI] Penjualan Tunai Meja (T3)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx3->id,
            'created_by'     => $cashier->id,
        ]);

        // 5. T4: Walk-in Transfer Bank Rp 180.000 (9 Cube @ 20k, HPP 108k)
        $tx4 = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $cust2->id,
            'type'                   => 'sale',
            'total_amount'           => 180000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 180000,
            'payment_method'         => 'transfer-bank',
            'initial_payment_method' => 'transfer-bank',
            'payment_status'         => 'paid',
            'order_status'           => 'completed',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 12:00:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] Penjualan Transfer Bank TelukJambe (T4)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $tx4->id,
            'product_id'     => $prodCube->id,
            'quantity'       => 9,
            'cost_price'     => 12000,
            'unit_price'     => 20000,
            'total_price'    => 180000,
        ]);
        CashFlow::withoutGlobalScopes()->create([
            'business_id'    => $businessId,
            'branch_id'      => $branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'category_id'    => $catIncome->id,
            'is_cogs'        => false,
            'payment_method' => 'transfer-bank',
            'funding_source' => 'owner',
            'affects_drawer' => 0,
            'amount'         => 180000,
            'description'    => '[SIMULASI] Penjualan Transfer Bank (T4)',
            'date'           => $date,
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx4->id,
            'created_by'     => $cashier->id,
        ]);

        // 6. T5: ACTIVE DELIVERING TRIP LIVE (SIM-TRIP-LIVE-01) SIAP DIUJI LANGSUNG OLEH KASIR TELUKJAMBE!
        // Driver sedang mengantar 2 Tube @ 12k = Rp 24.000 ke Customer B (yang punya saldo deposit Rp 10k)
        $txLive = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $businessId,
            'branch_id'              => $branch->id,
            'customer_id'            => $custB->id,
            'driver_id'              => $driver->id,
            'claimed_by_driver_id'    => $driver->id,
            'delivery_trip_code'     => 'SIM-TRIP-LIVE-01',
            'type'                   => 'sale',
            'total_amount'           => 24000,
            'deposit_used'           => 0,
            'net_payable_amount'     => 24000,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'delivery_status'        => 'delivering',
            'order_status'           => 'delivering',
            'status'                 => 'completed',
            'transaction_date'       => $date . ' 13:30:00',
            'created_by'             => $cashier->id,
            'notes'                  => '[SIMULASI] LIVE PENGIRIMAN DRIVER BERJALAN (Siap Uji Tombol Pelunasan & Deposit di Dashboard Kasir TelukJambe)',
        ]);
        TransactionDetail::withoutGlobalScopes()->create([
            'transaction_id' => $txLive->id,
            'product_id'     => $prodTube->id,
            'quantity'       => 2,
            'cost_price'     => 7000,
            'unit_price'     => 12000,
            'total_price'    => 24000,
        ]);

        // 7. Pengeluaran Laci TelukJambe (OpEx Rp 25.000)
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'expense',
            'category_id' => $catOpex->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 25000, 'description' => '[SIMULASI] Bensin Operasional TelukJambe', 'date' => $date,
            'reference_type' => 'manual', 'reference_id' => 'SIM-EXP-CBC-01', 'created_by' => $cashier->id,
        ]);

        // 8. Handover Setoran ke Owner Rp 150.000
        CashFlow::withoutGlobalScopes()->create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'type' => 'expense', 'flow_type' => 'cash_handover',
            'category_id' => $catNonUsaha->id, 'is_cogs' => false, 'payment_method' => 'cash', 'funding_source' => 'drawer',
            'affects_drawer' => 1, 'amount' => 150000, 'description' => '[SIMULASI] Setor Uang Laci TelukJambe ke Owner', 'date' => $date,
            'reference_type' => 'handover', 'reference_id' => 'SIM-HANDOVER-CBC-' . $dateCompact, 'created_by' => $cashier->id,
        ]);

        // 9. Tutup Shift Kasir TelukJambe (Expected: 150k + 290k - 175k = 265k, Actual: 267k -> Variance +Rp 2.000 OVER)
        $closingData = [
            'business_id'                => $businessId,
            'branch_id'                  => $branch->id,
            'user_id'                    => $cashier->id,
            'date'                       => $date,
            'shift_code'                 => 'SHIFT-CBC',
            'opening_cash'               => 150000,
            'cash_sales'                 => 240000,
            'cash_debt_collection'       => 20000,
            'cash_expenses'              => 25000,
            'cash_handover_amount'       => 150000,
            'cash_refunds'               => 0,
            'expected_drawer_cash'       => 265000,
            'actual_cash_counted'        => 267000,
            'variance'                   => 2000,
            'ending_cash_for_next_shift' => 267000,
            'notes'                      => '[SIMULASI] Tutup Shift TelukJambe (Selisih Lebih Rp 2.000)',
            'closed_at'                  => $date . ' 22:00:00',
            'closed_by'                  => $cashier->id,
        ];
        if (Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
            $closingData['external_reference'] = 'ACS-SUB-SIM-CBC-' . $dateCompact;
        }
        CashierShiftClosing::withoutGlobalScopes()->create($closingData);
    }

    /**
     * Cari user kasir di cabang bersangkutan.
     */
    protected function resolveCashier(int $businessId, int $branchId): User
    {
        return User::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('role', 'kasir')
                  ->orWhereJsonContains('secondary_roles', 'kasir');
            })
            ->first()
            ?? User::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where(function ($q) {
                    $q->where('role', 'kasir')
                      ->orWhereJsonContains('secondary_roles', 'kasir');
                })
                ->first()
            ?? User::withoutGlobalScopes()->where('branch_id', $branchId)->first()
            ?? User::withoutGlobalScopes()->where('business_id', $businessId)->first()
            ?? User::withoutGlobalScopes()->first();
    }

    /**
     * Cari user driver di cabang bersangkutan.
     */
    protected function resolveDriver(int $businessId, int $branchId): User
    {
        return User::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where(function ($q) {
                $q->where('role', 'driver')
                  ->orWhereJsonContains('secondary_roles', 'driver');
            })
            ->first()
            ?? User::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where(function ($q) {
                    $q->where('role', 'driver')
                      ->orWhereJsonContains('secondary_roles', 'driver');
                })
                ->first()
            ?? $this->resolveCashier($businessId, $branchId);
    }

    /**
     * Membersihkan record bertanda [SIMULASI] secara idempotent.
     */
    protected function cleanSimulationData(int $businessId, ?string $specificDate = null): int
    {
        $deletedCount = 0;

        // 1. Transaksi, Detail & Pergerakan Stok Transaksi
        $txQuery = Transaction::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('notes', 'LIKE', '%[SIMULASI]%')
                  ->orWhere('delivery_trip_code', 'LIKE', 'SIM-%')
                  ->orWhere('void_reason', 'LIKE', '%[SIMULASI]%');
            });

        if ($specificDate) {
            $pastDate = Carbon::parse($specificDate)->subDays(3)->format('Y-m-d');
            $txQuery->where(function ($q) use ($specificDate, $pastDate) {
                $q->whereDate('transaction_date', $specificDate)
                  ->orWhereDate('transaction_date', $pastDate);
            });
        }

        $txIds = (clone $txQuery)->pluck('id')->toArray();
        if (!empty($txIds)) {
            TransactionDetail::withoutGlobalScopes()->whereIn('transaction_id', $txIds)->delete();
            StockMovement::withoutGlobalScopes()
                ->where('reference_type', 'transaction')
                ->whereIn('reference_id', $txIds)
                ->delete();
            $deletedCount += $txQuery->delete();
        }

        // 2. Pergerakan Stok mandiri bertanda [SIMULASI]
        $smQuery = StockMovement::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('notes', 'LIKE', '%[SIMULASI]%');
        $deletedCount += $smQuery->delete();

        // 3. Arus Kas (Cash Flow)
        $cfQuery = CashFlow::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('description', 'LIKE', '%[SIMULASI]%')
                  ->orWhere('reference_id', 'LIKE', 'SIM-%')
                  ->orWhere('reference_id', 'LIKE', 'ATT-SIM-%');
            });

        if ($specificDate) {
            $cfQuery->whereDate('date', $specificDate);
        }
        $deletedCount += $cfQuery->delete();

        // 4. Modal Awal Laci
        $obQuery = CashierOpeningBalance::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('notes', 'LIKE', '%[SIMULASI]%');

        if ($specificDate) {
            $obQuery->whereDate('date', $specificDate);
        }
        $deletedCount += $obQuery->delete();

        // 5. Tutup Shift Kasir
        $scQuery = CashierShiftClosing::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('notes', 'LIKE', '%[SIMULASI]%')
                  ->orWhere(function ($sub) {
                      if (Schema::hasColumn('cashier_shift_closings', 'external_reference')) {
                          $sub->where('external_reference', 'LIKE', 'ACS-SUB-SIM-%');
                      }
                  });
            });

        if ($specificDate) {
            $scQuery->whereDate('date', $specificDate);
        }
        $deletedCount += $scQuery->delete();

        // 6. Beban Holding Owner
        $oeQuery = OwnerExpense::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('title', 'LIKE', '%[SIMULASI]%')
                  ->orWhere('description', 'LIKE', '%[SIMULASI]%')
                  ->orWhere('notes', 'LIKE', '%[SIMULASI]%');
            });

        if ($specificDate) {
            $oeQuery->whereDate('date', $specificDate);
        }
        $deletedCount += $oeQuery->delete();

        // 7. Ledger Deposit Simulasi
        $depLogQuery = CustomerDepositLog::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('notes', 'LIKE', '%[SIMULASI]%');
            });
        $deletedCount += $depLogQuery->delete();

        // 8. Reset Saldo Pelanggan Simulasi
        Customer::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('name', 'LIKE', '%[SIMULASI]%')
            ->update(['deposit_balance' => 0]);

        // 9. Jika pembersihan penuh (--clean tanpa tanggal), hapus master data simulasi
        if (!$specificDate) {
            Customer::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('name', 'LIKE', '%[SIMULASI]%')
                ->delete();

            $simProducts = Product::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where(function ($q) {
                    $q->where('name', 'LIKE', '%[SIMULASI]%')
                      ->orWhere('sku', 'LIKE', 'SIM-%');
                })
                ->get();

            foreach ($simProducts as $p) {
                Inventory::withoutGlobalScopes()->where('product_id', $p->id)->delete();
                $p->delete();
            }

            ExpenseCategory::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('name', 'LIKE', '%[SIMULASI]%')
                ->delete();
        }

        return $deletedCount;
    }
}
