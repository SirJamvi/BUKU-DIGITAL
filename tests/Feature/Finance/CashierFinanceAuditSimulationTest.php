<?php

namespace Tests\Feature\Finance;

use App\Exceptions\PeriodClosedException;
use App\Models\Branch;
use App\Models\BranchMonthlyClosing;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\CashierShiftClosing;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Acs\AcsCashDrawerSyncService;
use App\Services\Admin\DashboardService as AdminDashboardService;
use App\Services\Admin\FinancialService;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\CashierClosingService;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CashierFinanceAuditSimulationTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branch;
    protected User $cashier;
    protected User $admin;
    protected Customer $customer;
    protected Product $product;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Bisnis & Cabang
        $this->business = Business::create(['name' => 'Bisnis Es Kristal Test']);
        $this->branch = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Utama',
            'code'        => 'CB-01',
            'is_main'     => true,
            'is_active'   => true,
        ]);

        // 2. Setup Pengguna
        $this->admin = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Admin Owner',
            'email'       => 'admin@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'admin',
        ]);

        $this->cashier = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Dika Kasir',
            'email'       => 'adincrystal43@gmail.com',
            'password'    => bcrypt('password'),
            'role'        => 'cashier',
        ]);

        // 3. Setup Customer & Produk
        $this->customer = Customer::create([
            'business_id' => $this->business->id,
            'name'        => 'Pelanggan Setia',
            'phone'       => '081234567890',
        ]);

        $this->product = Product::create([
            'business_id' => $this->business->id,
            'name'        => 'Es Kristal 10kg',
            'cost_price'  => 8000,
            'price'       => 15000,
            'unit'        => 'Bag',
            'is_service'  => false,
        ]);

        $this->inventory = Inventory::create([
            'business_id'   => $this->business->id,
            'branch_id'     => $this->branch->id,
            'product_id'    => $this->product->id,
            'cost_price'    => 8000,
            'current_stock' => 100,
            'min_stock'     => 10,
        ]);

        PaymentMethod::create([
            'business_id' => $this->business->id,
            'name'        => 'Tunai',
            'slug'        => 'cash',
            'is_active'   => true,
        ]);

        PaymentMethod::create([
            'business_id' => $this->business->id,
            'name'        => 'Transfer Bank',
            'slug'        => 'transfer-bank',
            'is_active'   => true,
        ]);

        ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Operasional Harian',
            'type'          => 'Operasional',
            'category_type' => 'Operasional',
            'is_cogs'       => 0,
            'is_active'     => 1,
        ]);
    }

    /**
     * Skenario 1: Baseline shift normal regression
     * Modal Awal: Rp 100.000
     * Penjualan Meja Tunai: Rp 500.000
     * Pelunasan Kasbon Tunai: Rp 120.000
     * Pengeluaran Operasional Laci: Rp 65.000
     * Expected Drawer Cash = 100k + 500k + 120k - 65k = Rp 655.000
     */
    public function test_01_baseline_shift_normal_regression(): void
    {
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);

        $today = now()->toDateString();

        // Modal Awal
        CashierOpeningBalance::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'user_id'     => $this->cashier->id,
            'amount'      => 100000,
            'date'        => $today,
        ]);

        // Penjualan Tunai Meja (Rp 500k)
        $tx = Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $this->customer->id,
            'total_amount'           => 500000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'status'                 => 'completed',
            'transaction_date'       => now(),
            'created_by'             => $this->cashier->id,
        ]);

        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx->id,
            'category_id'    => 1,
            'amount'         => 500000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Penjualan POS #' . $tx->id,
            'date'           => $today,
            'created_by'     => $this->cashier->id,
        ]);

        // Pelunasan Kasbon Tunai (Rp 120k)
        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'reference_type' => 'transaction',
            'reference_id'   => '999',
            'category_id'    => 1,
            'amount'         => 120000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Pelunasan Kasbon Nota #999',
            'date'           => $today,
            'created_by'     => $this->cashier->id,
        ]);

        // Pengeluaran Operasional Laci (Rp 65k: bensin & makan)
        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'expense',
            'flow_type'      => 'expense',
            'reference_type' => 'manual',
            'category_id'    => 1,
            'amount'         => 65000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Beli bensin dan makan',
            'date'           => $today,
            'created_by'     => $this->cashier->id,
        ]);

        // 1. Verifikasi via Kasir DashboardService
        $kasirService = app(KasirDashboardService::class);
        $kasirData = $kasirService->getDashboardData();
        $this->assertEquals(655000.0, (float) $kasirData['myNetCashToday']);

        // 2. Verifikasi via AcsCashDrawerSyncService
        $acsService = app(AcsCashDrawerSyncService::class);
        $acsResult = $acsService->getBranchDrawerSummary($this->business->id, $today, 'CB-01');
        $this->assertNotEmpty($acsResult);
        $this->assertEquals(655000.0, (float) $acsResult[0]['expected_drawer_cash']);
    }

    /**
     * Skenario 2: Category type, Cash Handover & Piutang Karyawan
     * Cash handover Rp 200k memotong laci, tapi TIDAK masuk P&L expense.
     * Kasbon Karyawan Rp 50k (Piutang Karyawan) tidak memotong Laba Bersih operasional.
     */
    public function test_02_category_type_cash_handover_and_piutang_karyawan(): void
    {
        Auth::login($this->admin);

        $piutangCat = ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Kasbon Pinjaman Karyawan',
            'type'          => 'Operasional',
            'category_type' => 'Piutang Karyawan',
            'is_cogs'       => 0,
            'is_active'     => 1,
        ]);

        // Catat mutasi cash_handover Rp 200.000 via CashierClosingService
        $closingService = app(CashierClosingService::class);
        $handover = $closingService->recordCashHandover(
            $this->business->id,
            $this->branch->id,
            $this->cashier->id,
            200000,
            'Setor ke BCA Owner'
        );

        $this->assertEquals('cash_handover', $handover->flow_type);
        $this->assertTrue((bool) $handover->affects_drawer);

        // Catat kasbon karyawan Rp 50.000
        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'expense',
            'flow_type'      => 'expense',
            'reference_type' => 'manual',
            'category_id'    => $piutangCat->id,
            'amount'         => 50000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Kasbon pinjaman karyawan',
            'date'           => now()->toDateString(),
            'created_by'     => $this->cashier->id,
        ]);

        // Verifikasi pada FinancialService
        $financialService = app(FinancialService::class);
        $report = $financialService->getFinancialReport([]);

        // Total beban operasional murni (is_cogs=0 & category_type=Operasional) TIDAK boleh mencakup 200k (handover) maupun 50k (piutang)
        $this->assertEquals(0.0, (float) $report['total_expense']);
    }

    /**
     * Skenario 3: Gaji kasir tanggal 15 & 30 dibayar eksternal & Idempotency
     * Gaji dicatat sebagai beban bulanan, tetapi TIDAK mengurangi saldo fisik kas laci.
     * Request ganda dengan reference_id yang sama tidak menduplikasi data.
     */
    public function test_03_cashier_own_salary_on_15_and_30_and_idempotency(): void
    {
        ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Beban Gaji Karyawan',
            'type'          => 'Operasional',
            'category_type' => 'Operasional',
            'is_cogs'       => 0,
            'is_active'     => 1,
        ]);

        $payload = [
            'user_id'      => $this->cashier->id,
            'amount'       => 2500000,
            'description'  => 'Gaji Periode 15-30 Nov 2026',
            'date'         => now()->toDateString(),
            'reference_id' => 'PAY-202611-001',
        ];

        // Hit sync gaji pertama kali
        $response1 = $this->postJson('/api/v1/sync/salary', $payload);
        $response1->assertStatus(200);

        // Hit sync gaji kedua kali dengan reference_id sama (Idempotent)
        $response2 = $this->postJson('/api/v1/sync/salary', $payload);
        $response2->assertStatus(200);
        $response2->assertJson(['message' => 'Data already synced']);

        // Pastikan hanya 1 record tersimpan
        $count = CashFlow::where('reference_id', 'ATT-PAY-202611-001')->count();
        $this->assertEquals(1, $count);

        $salaryFlow = CashFlow::where('reference_id', 'ATT-PAY-202611-001')->first();
        $this->assertEquals('salary_external', $salaryFlow->flow_type);
        $this->assertEquals('external', $salaryFlow->funding_source);
        $this->assertFalse((bool) $salaryFlow->affects_drawer);

        // Verifikasi tidak memotong laci kasir
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);
        $kasirData = app(KasirDashboardService::class)->getDashboardData();
        $this->assertEquals(0.0, (float) $kasirData['myExpensesToday']);
    }

    /**
     * Skenario 4: Pembelian stok es luar menggunakan uang laci (is_cogs = 1)
     * Memotong laci kasir, tetapi dikecualikan dari beban operasional murni (tidak double-counting).
     */
    public function test_04_external_stock_purchase_is_cogs_1_from_drawer(): void
    {
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);

        $cogsCat = ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Refill Es Batu Luar',
            'type'          => 'Operasional',
            'category_type' => 'HPP',
            'is_cogs'       => 1,
            'is_active'     => 1,
        ]);

        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'expense',
            'flow_type'      => 'stock_purchase',
            'reference_type' => 'manual',
            'category_id'    => $cogsCat->id,
            'is_cogs'        => 1,
            'amount'         => 80000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Pembelian 10 bag es dari supplier luar [Laci Kasir]',
            'date'           => now()->toDateString(),
            'created_by'     => $this->cashier->id,
        ]);

        // 1. Memotong laci kasir
        $kasirData = app(KasirDashboardService::class)->getDashboardData();
        $this->assertEquals(80000.0, (float) $kasirData['myExpensesToday']);

        // 2. Dikecualikan dari beban operasional murni pada Laba Rugi
        Auth::login($this->admin);
        $financialService = app(FinancialService::class);
        $report = $financialService->getFinancialReport([]);
        $this->assertEquals(0.0, (float) $report['total_expense']);
    }

    /**
     * Skenario 5: Penghapusan ketergantungan LIKE '%Pelunasan%', pelunasan non-tunai & kredit history
     * Transaksi kasbon yang dilunasi dengan Transfer Bank mempertahankan initial_payment_method='kasbon',
     * tidak masuk ke kas fisik laci, dan masuk ke kategori non-tunai.
     */
    public function test_05_removal_of_like_pelunasan_non_cash_kasbon_and_credit_history(): void
    {
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);

        $tx = Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $this->customer->id,
            'total_amount'           => 150000,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'status'                 => 'completed',
            'transaction_date'       => now(),
            'created_by'             => $this->cashier->id,
        ]);

        // Pelunasan via Transfer Bank tanpa kata-kata 'Pelunasan' di deskripsi
        $tx->update([
            'payment_status'            => 'paid',
            'settlement_payment_method' => 'transfer-bank',
            'payment_method'            => 'transfer-bank',
            'settled_at'                => now(),
        ]);

        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'income',
            'flow_type'      => 'debt_settlement',
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx->id,
            'category_id'    => 1,
            'amount'         => 150000,
            'payment_method' => 'transfer-bank',
            'funding_source' => 'owner',
            'affects_drawer' => 0,
            'description'    => 'Setoran tagihan kredit faktur #' . $tx->id,
            'date'           => now()->toDateString(),
            'created_by'     => $this->cashier->id,
        ]);

        // Verifikasi histori kredit utuh
        $freshTx = $tx->fresh();
        $this->assertEquals('kasbon', $freshTx->initial_payment_method);
        $this->assertEquals('transfer-bank', $freshTx->settlement_payment_method);
        $this->assertNotNull($freshTx->settled_at);

        // Verifikasi kas laci fisik tidak bertambah, namun non-tunai bertambah
        $kasirData = app(KasirDashboardService::class)->getDashboardData();
        $this->assertEquals(0.0, (float) $kasirData['cashSalesAmount']);
        $this->assertEquals(0.0, (float) $kasirData['pelunasanCashAmount']);
        $this->assertEquals(150000.0, (float) $kasirData['nonCashDebtCollectionAmount']);
    }

    /**
     * Skenario 6: ACS Shift Time Filter, Soft Void & Contra-Entry Cash Flow (Same-Day Void Protection)
     * Pembatalan transaksi (soft void) membuat contra-entry cash_refund.
     * Saldo laci kasir kembali utuh ke saldo sebelum transaksi (tidak terpotong dua kali).
     */
    public function test_06_acs_shift_time_filter_cancelled_transactions_and_void_contra_entry(): void
    {
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);

        $today = now()->toDateString();

        // 1. Modal Awal Rp 50.000 (Baseline saldo sebelum transaksi)
        CashierOpeningBalance::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'user_id'     => $this->cashier->id,
            'amount'      => 50000,
            'date'        => $today,
        ]);

        // 2. Buat transaksi penjualan tunai meja Rp 100.000
        $tx = Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $this->customer->id,
            'total_amount'           => 100000,
            'payment_method'         => 'cash',
            'initial_payment_method' => 'cash',
            'payment_status'         => 'paid',
            'status'                 => 'completed',
            'transaction_date'       => now(),
            'created_by'             => $this->cashier->id,
        ]);

        $tx->details()->create([
            'product_id'  => $this->product->id,
            'quantity'    => 5,
            'cost_price'  => 8000,
            'unit_price'  => 20000,
            'total_price' => 100000,
        ]);

        CashFlow::create([
            'business_id'    => $this->business->id,
            'branch_id'      => $this->branch->id,
            'type'           => 'income',
            'flow_type'      => 'sale_direct',
            'reference_type' => 'transaction',
            'reference_id'   => (string) $tx->id,
            'category_id'    => 1,
            'amount'         => 100000,
            'payment_method' => 'cash',
            'funding_source' => 'drawer',
            'affects_drawer' => 1,
            'description'    => 'Penjualan #' . $tx->id,
            'date'           => $today,
            'created_by'     => $this->cashier->id,
        ]);

        // Verifikasi saldo sebelum void adalah Rp 150.000 (50k modal + 100k penjualan)
        $kasirDataBefore = app(KasirDashboardService::class)->getDashboardData();
        $this->assertEquals(150000.0, (float) $kasirDataBefore['myNetCashToday']);

        // 3. Batalkan transaksi (Same-Day Void) via AdminTransactionService
        Auth::login($this->admin);
        $txService = app(AdminTransactionService::class);
        $success = $txService->deleteTransaction($tx, 'Customer salah pesan ukuran');
        $this->assertTrue($success);

        // A. Verifikasi status transaksi & audit void
        $voidedTx = $tx->fresh();
        $this->assertEquals('cancelled', $voidedTx->status);
        $this->assertEquals('Customer salah pesan ukuran', $voidedTx->void_reason);
        $this->assertEquals($this->admin->id, $voidedTx->voided_by);
        $this->assertNotNull($voidedTx->voided_at);

        // B. Verifikasi StockMovement bertipe void_return
        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'void_return')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(5, (int) $movement->quantity);

        // C. Verifikasi Contra-entry di cash_flow
        $contraEntry = CashFlow::where('reference_id', (string) $tx->id)
            ->where('flow_type', 'cash_refund')
            ->first();
        $this->assertNotNull($contraEntry);
        $this->assertEquals('expense', $contraEntry->type);
        $this->assertTrue((bool) $contraEntry->affects_drawer);
        $this->assertEquals(100000.0, (float) $contraEntry->amount);

        // D. [SAME-DAY VOID PROTECTION ASSERTION]
        // Saldo laci kasir HARUS KEMBALI UTUH ke saldo awal Rp 50.000 (TIDAK terpotong dua kali menjadi Rp -50.000)
        Auth::login($this->cashier);
        app(BranchContext::class)->setBranchId($this->branch->id);
        $kasirDataAfter = app(KasirDashboardService::class)->getDashboardData();
        $this->assertEquals(100000.0, (float) $kasirDataAfter['cashSalesAmount']);
        $this->assertEquals(100000.0, (float) $kasirDataAfter['myExpensesToday']);
        $this->assertEquals(50000.0, (float) $kasirDataAfter['myNetCashToday'], 'Bug Same-Day Void teratasi: Saldo laci kembali utuh ke modal awal Rp 50.000');

        // E. Verifikasi pada AcsCashDrawerSyncService (Expected drawer tetap Rp 50.000)
        $acsService = app(AcsCashDrawerSyncService::class);
        $acsSummary = $acsService->getBranchDrawerSummary($this->business->id, $today, 'CB-01');
        $this->assertEquals(100000.0, (float) $acsSummary[0]['total_cash_sales']);
        $this->assertEquals(100000.0, (float) $acsSummary[0]['total_cash_expenses']);
        $this->assertEquals(50000.0, (float) $acsSummary[0]['expected_drawer_cash'], 'ACS Drawer Summary Expected Cash pas Rp 50.000');

    /**
     * Skenario 7: Accounting Period Lock, Reopen & Cashier Shift Closing (Variance)
     * Mutasi ditolak jika bulan sudah ditutup buku;
     * reopenBranchClosing() mengizinkan kembali transaksi;
     * closeDailyShift() menyimpan Over/Short (variance) secara permanen.
     */
    public function test_07_period_lock_branch_monthly_closing_and_daily_shift_closing(): void
    {
        Auth::login($this->admin);

        $now = now();
        $month = (int) $now->format('m');
        $year = (int) $now->format('Y');

        // 1. Simulasikan Closing Bulanan Cabang selesai (completed)
        $closing = BranchMonthlyClosing::create([
            'business_id'     => $this->business->id,
            'branch_id'       => $this->branch->id,
            'period_month'    => $month,
            'period_year'     => $year,
            'monthly_income'  => 10000000,
            'monthly_cogs'    => 5000000,
            'gross_profit'    => 5000000,
            'monthly_expense' => 2000000,
            'net_profit'      => 3000000,
            'status'          => 'completed',
            'closed_at'       => now(),
            'closed_by'       => $this->admin->id,
        ]);

        // Coba buat handover pada bulan yang sudah ditutup -> harus melempar PeriodClosedException
        $closingService = app(CashierClosingService::class);
        $threwException = false;
        try {
            $closingService->recordCashHandover(
                $this->business->id,
                $this->branch->id,
                $this->cashier->id,
                50000
            );
        } catch (PeriodClosedException $e) {
            $threwException = true;
        }
        $this->assertTrue($threwException, 'Seharusnya gagal karena periode pembukuan sudah di-close!');

        // 2. Reopen Closing Bulanan
        $financialService = app(FinancialService::class);
        $reopened = $financialService->reopenBranchClosing($closing->id, 'Perbaikan nota kasir susulan', $this->admin->id);
        $this->assertEquals('draft', $reopened->status);

        // Setelah di-reopen, handover kas harus berhasil tanpa exception
        $handover = $closingService->recordCashHandover(
            $this->business->id,
            $this->branch->id,
            $this->cashier->id,
            50000,
            'Handover setelah reopen'
        );
        $this->assertNotNull($handover);

        // 3. Simpan Closing Shift Harian & Catat Variance (Over/Short)
        // Set actual cash counted Rp 45.000 (selisih kurang Rp 5.000 dari expected yang minus 50.000)
        $shiftClosing = $closingService->closeDailyShift([
            'business_id'        => $this->business->id,
            'branch_id'          => $this->branch->id,
            'user_id'            => $this->cashier->id,
            'date'               => now()->toDateString(),
            'actual_cash_counted'=> 0.00,
            'notes'              => 'Shift pagi selesai',
        ]);

        $this->assertInstanceOf(CashierShiftClosing::class, $shiftClosing);
        $this->assertEquals(50000.0, (float) $shiftClosing->cash_handover_amount);
        $this->assertEquals(-50000.0, (float) $shiftClosing->expected_drawer_cash);
        $this->assertEquals(50000.0, (float) $shiftClosing->variance); // 0 - (-50000)
    }
}
