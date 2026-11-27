<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\CashierOpeningBalance;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Admin\DashboardService as AdminDashboardService;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DashboardTransactionSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_and_admin_dashboard_transaction_summary_are_100_percent_reconciled(): void
    {
        $business = Business::create(['name' => 'Es Kristal Rekonsiliasi']);
        $branch = Branch::create([
            'business_id' => $business->id,
            'name'        => 'Cabang Pusat',
            'code'        => 'CB-PUSAT',
            'is_main'     => true,
            'is_active'   => true,
        ]);

        $admin = User::create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Admin Pusat',
            'email'       => 'admin-rec@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'admin',
            'is_active'   => true,
        ]);

        $kasir = User::create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Budi Santoso',
            'email'       => 'budi-rec@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);

        $customer = Customer::create([
            'business_id'     => $business->id,
            'name'            => 'Toko Sejahtera',
            'phone'           => '081234567890',
            'deposit_balance' => 820000,
        ]);

        PaymentMethod::create(['business_id' => $business->id, 'name' => 'Cash', 'slug' => 'cash', 'is_active' => true]);
        PaymentMethod::create(['business_id' => $business->id, 'name' => 'DANA', 'slug' => 'dana', 'is_active' => true]);
        PaymentMethod::create(['business_id' => $business->id, 'name' => 'Transfer Bank', 'slug' => 'transfer-bank', 'is_active' => true]);

        $opsCat = ExpenseCategory::create([
            'business_id'   => $business->id,
            'name'          => 'Operasional',
            'type'          => 'Operasional',
            'category_type' => 'Operasional',
            'is_cogs'       => 0,
            'is_active'     => 1,
        ]);

        $today = Carbon::today('Asia/Jakarta');
        $todayStr = $today->toDateString();

        // Modal awal laci: 500.000
        CashierOpeningBalance::create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'user_id'     => $kasir->id,
            'amount'      => 500000,
            'date'        => $todayStr,
        ]);

        // 1. Penjualan Langsung: Cash 2.450.000, DANA 850.000, Transfer 1.200.000, Potong Deposit 300.000
        $txSale = Transaction::create([
            'business_id'      => $business->id,
            'branch_id'        => $branch->id,
            'type'             => 'sale',
            'customer_id'      => $customer->id,
            'total_amount'     => 4800000,
            'deposit_used'     => 300000,
            'net_payable_amount' => 4500000,
            'payment_method'   => 'cash',
            'payment_status'   => 'paid',
            'status'           => 'completed',
            'transaction_date' => $today->copy()->setHour(10),
            'created_by'       => $kasir->id,
        ]);

        $createCf = function (array $attrs) use ($business, $branch, $opsCat, $todayStr, $kasir) {
            return CashFlow::create(array_merge([
                'business_id' => $business->id,
                'branch_id'   => $branch->id,
                'category_id' => $opsCat->id,
                'date'        => $todayStr,
                'created_by'  => $kasir->id,
            ], $attrs));
        };

        $createCf(['type' => 'income', 'flow_type' => 'sale_direct', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 2450000, 'description' => 'Penjualan Cash']);
        $createCf(['type' => 'income', 'flow_type' => 'sale_direct', 'payment_method' => 'dana', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 850000, 'description' => 'Penjualan DANA']);
        $createCf(['type' => 'income', 'flow_type' => 'sale_direct', 'payment_method' => 'transfer-bank', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 1200000, 'description' => 'Penjualan Transfer']);

        CustomerDepositLog::create([
            'business_id'    => $business->id,
            'branch_id'      => $branch->id,
            'customer_id'    => $customer->id,
            'transaction_id' => $txSale->id,
            'type'           => 'debit',
            'amount'         => 300000,
            'balance_before' => 1120000,
            'balance_after'  => 820000,
            'payment_method' => 'deposit',
            'notes'          => 'Penggunaan Deposit Transaksi #' . $txSale->id,
            'created_by'     => $kasir->id,
        ]);

        // 2. Pelunasan Kasbon: Cash 650.000, DANA 350.000, Transfer 900.000, Potong Deposit 100.000
        $createCf(['type' => 'income', 'flow_type' => 'debt_settlement', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 650000, 'description' => 'Pelunasan Kasbon Cash']);
        $createCf(['type' => 'income', 'flow_type' => 'debt_settlement', 'payment_method' => 'dana', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 350000, 'description' => 'Pelunasan Kasbon DANA']);
        $createCf(['type' => 'income', 'flow_type' => 'debt_settlement', 'payment_method' => 'transfer-bank', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 900000, 'description' => 'Pelunasan Kasbon Transfer']);

        CustomerDepositLog::create([
            'business_id'    => $business->id,
            'branch_id'      => $branch->id,
            'customer_id'    => $customer->id,
            'transaction_id' => $txSale->id,
            'type'           => 'debit',
            'amount'         => 100000,
            'balance_before' => 920000,
            'balance_after'  => 820000,
            'payment_method' => 'deposit',
            'notes'          => 'Pelunasan Kasbon (Bulk) #000001',
            'created_by'     => $kasir->id,
        ]);

        // 3. Deposit Masuk: Cash 400.000, DANA 250.000, Transfer 750.000
        $createCf(['type' => 'income', 'flow_type' => 'customer_deposit', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 400000, 'description' => 'Deposit Cash']);
        $createCf(['type' => 'income', 'flow_type' => 'customer_deposit', 'payment_method' => 'dana', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 250000, 'description' => 'Deposit DANA']);
        $createCf(['type' => 'income', 'flow_type' => 'customer_deposit', 'payment_method' => 'transfer-bank', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 750000, 'description' => 'Deposit Transfer']);

        // 4. Kasbon Baru Hari Ini: 950.000
        Transaction::create([
            'business_id'      => $business->id,
            'branch_id'        => $branch->id,
            'type'             => 'sale',
            'customer_id'      => $customer->id,
            'total_amount'     => 950000,
            'payment_method'   => 'kasbon',
            'payment_status'   => 'pending',
            'status'           => 'completed',
            'transaction_date' => $today->copy()->setHour(11),
            'created_by'       => $kasir->id,
        ]);

        // 5. Kas Keluar Laci (total 1.500.000) + Non-Laci (400.000)
        $createCf(['type' => 'expense', 'flow_type' => 'expense', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 220000, 'description' => 'BBM Driver']);
        $createCf(['type' => 'expense', 'flow_type' => 'stock_purchase', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 150000, 'description' => 'Beli Stok Es']);
        $createCf(['type' => 'expense', 'flow_type' => 'cash_handover', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 1000000, 'description' => 'Setoran ke Owner']);
        $createCf(['type' => 'expense', 'flow_type' => 'cash_refund', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 50000, 'description' => 'Void Nota Cash']);
        $createCf(['type' => 'expense', 'flow_type' => 'deposit_refund', 'payment_method' => 'cash', 'funding_source' => 'drawer', 'affects_drawer' => 1, 'amount' => 80000, 'description' => 'Refund Deposit Cash']);

        $createCf(['type' => 'expense', 'flow_type' => 'expense', 'payment_method' => 'transfer-bank', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 300000, 'description' => 'Beban Non-Laci']);
        $createCf(['type' => 'expense', 'flow_type' => 'deposit_refund', 'payment_method' => 'transfer-bank', 'funding_source' => 'owner', 'affects_drawer' => 0, 'amount' => 100000, 'description' => 'Refund Deposit Transfer']);

        // --- ASSERT KASIR DASHBOARD SERVICE ---
        Auth::login($kasir);
        app(BranchContext::class)->setBranch($branch->id, $business->id);

        $kasirData = app(KasirDashboardService::class)->getDashboardData($kasir->id);
        $kTs = $kasirData['transactionSummary'];

        $this->assertEquals(2500000.0, (float) $kasirData['myNetCashToday']);
        $this->assertEquals(3500000.0, (float) $kTs['rows']['inflow_gross']['cash']);
        $this->assertEquals(1450000.0, (float) $kTs['rows']['inflow_gross']['dana']);
        $this->assertEquals(2850000.0, (float) $kTs['rows']['inflow_gross']['transfer']);
        $this->assertEquals(7800000.0, (float) $kTs['rows']['inflow_gross']['total_money']);
        $this->assertEquals(400000.0, (float) $kTs['rows']['inflow_gross']['deposit_used']);
        $this->assertEquals(230000.0, (float) $kTs['rows']['refunds']['total_money']);
        $this->assertEquals(7570000.0, (float) $kTs['rows']['inflow_net']['total_money']);
        $this->assertEquals(5750000.0, (float) $kTs['sales_kasbon']['omset_today']);
        $this->assertEquals(950000.0, (float) $kTs['sales_kasbon']['kasbon_new_today']);
        $this->assertEquals(1400000.0, (float) $kTs['deposit']['in_total']);
        $this->assertEquals(820000.0, (float) $kTs['deposit']['net_change']);
        $this->assertEquals(1500000.0, (float) $kTs['drawer']['out_total']);
        $this->assertEquals(400000.0, (float) $kTs['non_drawer']['total']);

        // --- ASSERT ADMIN DASHBOARD SERVICE 100% IDENTICAL ---
        Auth::login($admin);
        app(BranchContext::class)->setBranch($branch->id, $business->id);

        $adminService = app(AdminDashboardService::class);
        $adminData = $adminService->getDashboardData($today);
        $aTs = $adminData['transactionSummary'];

        $this->assertEquals($kTs['rows']['inflow_gross']['total_money'], $aTs['rows']['inflow_gross']['total_money']);
        $this->assertEquals($kTs['rows']['inflow_net']['total_money'], $aTs['rows']['inflow_net']['total_money']);
        $this->assertEquals($kTs['deposit']['in_total'], $aTs['deposit']['in_total']);
        $this->assertEquals($kTs['deposit']['used_total'], $aTs['deposit']['used_total']);
        $this->assertEquals(950000.0, (float) $adminData['totalActiveKasbonAmount']);
        $this->assertEquals(820000.0, (float) $adminData['totalActiveDepositBalance']);

        $cashierMonitoring = $adminService->getCashierCashOnHandData($business->id, $today);
        $this->assertCount(1, $cashierMonitoring);
        $cTs = $cashierMonitoring[0]['transaction_summary'];

        $this->assertEquals($kTs['drawer']['expected_drawer'], $cTs['drawer']['expected_drawer']);
        $this->assertEquals($kTs['rows']['inflow_net']['total_money'], $cTs['rows']['inflow_net']['total_money']);
        $this->assertEquals($kTs['deposit']['in_dana'], $cashierMonitoring[0]['deposit_dana']);
        $this->assertEquals($kTs['deposit']['in_transfer'], $cashierMonitoring[0]['deposit_transfer']);

        // --- ASSERT FILTERED SUMMARY, BREAKDOWN & DRILLDOWN ---
        $summaryService = app(\App\Services\Accounting\DailyTransactionSummaryService::class);
        $filtered = $summaryService->getFilteredSummary($business->id, $today, $branch->id, $kasir->id);
        $this->assertEquals(2500000.0, (float) $filtered['drawer']['expected_drawer']);

        $breakdown = $summaryService->getBreakdownByBranchAndCashier($business->id, $today, $branch->id);
        $this->assertCount(1, $breakdown['by_branch']);
        $this->assertCount(1, $breakdown['by_cashier']);
        $this->assertEquals(7570000.0, (float) $breakdown['by_branch'][0]['inflow_net']);
        $this->assertEquals(7570000.0, (float) $breakdown['by_cashier'][0]['inflow_net']);

        $refundItems = $summaryService->getDrilldownItems($business->id, $today, 'refunds', 'all', $branch->id, $kasir->id);
        $this->assertCount(3, $refundItems);
        $this->assertEquals(230000.0, (float) array_sum(array_column($refundItems, 'amount')));

        $kasbonItems = $summaryService->getDrilldownItems($business->id, $today, 'kasbon_new', 'all', $branch->id, $kasir->id);
        $this->assertCount(1, $kasbonItems);
        $this->assertEquals(950000.0, (float) $kasbonItems[0]['amount']);
    }
}
