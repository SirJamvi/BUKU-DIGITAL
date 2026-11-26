<?php

namespace Tests\Feature\Kasir;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Acs\AcsCashDrawerSyncService;
use App\Services\Admin\DashboardService as AdminDashboardService;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\CashierClosingService;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Services\ProofUploadService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class KasbonDepositTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branch;
    protected User $kasir;
    protected User $admin;
    protected Customer $customer;
    protected ExpenseCategory $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'      => 'Admin Test',
            'email'     => 'admin-kasbon@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->business = Business::create([
            'name'     => 'Adin Crystal Test',
            'owner_id' => $this->admin->id,
        ]);
        $this->admin->update(['business_id' => $this->business->id]);

        $this->branch = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Pusat',
            'code'        => 'CB-01',
            'is_main'     => true,
            'is_active'   => true,
            'manager_id'  => $this->admin->id,
        ]);
        $this->admin->update(['branch_id' => $this->branch->id]);

        $this->kasir = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Kasir Test',
            'email'       => 'kasir-kasbon@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $this->kasir->syncBranches([$this->branch->id], $this->branch->id);

        $this->customer = Customer::create([
            'business_id'     => $this->business->id,
            'name'            => 'Mitra Kasbon',
            'phone'           => '081234567890',
            'join_date'       => now()->toDateString(),
            'deposit_balance' => 90000,
            'total_purchases' => 0,
            'status'          => 'active',
            'created_by'      => $this->admin->id,
        ]);

        $this->incomeCategory = ExpenseCategory::create([
            'business_id'   => $this->business->id,
            'name'          => 'Pendapatan Penjualan',
            'type'          => 'income',
            'category_type' => 'Operasional',
            'is_cogs'       => 0,
            'is_active'     => 1,
        ]);
    }

    protected function createPendingKasbon(
        float $totalAmount = 20000,
        ?int $customerId = null,
        float $depositUsed = 0,
        ?float $netPayable = null
    ): Transaction {
        $net = $netPayable ?? max(0, $totalAmount - $depositUsed);

        return Transaction::create([
            'business_id'            => $this->business->id,
            'branch_id'              => $this->branch->id,
            'type'                   => 'sale',
            'customer_id'            => $customerId,
            'total_amount'           => $totalAmount,
            'deposit_used'           => $depositUsed,
            'net_payable_amount'     => $net,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'status'                 => 'completed',
            'transaction_date'       => now()->subDay(),
            'created_by'             => $this->kasir->id,
        ]);
    }

    public function test_pay_kasbon_with_deposit_blocks_when_selected_total_exceeds_deposit_balance(): void
    {
        $txs = collect();
        for ($i = 0; $i < 5; $i++) {
            $txs->push($this->createPendingKasbon(20000, $this->customer->id));
        }

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => $txs->pluck('id')->all(),
                'payment_method'  => 'deposit',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->customer->refresh();
        $this->assertEquals(90000.0, (float) $this->customer->deposit_balance);
        $this->assertEquals(5, Transaction::withoutGlobalScopes()->where('payment_status', 'pending')->count());
        $this->assertEquals(0, CustomerDepositLog::count());
        $this->assertEquals(0, CashFlow::count());
    }

    public function test_pay_kasbon_with_deposit_settles_four_of_five_invoices_and_keeps_remaining_deposit(): void
    {
        $txs = collect();
        for ($i = 0; $i < 5; $i++) {
            $txs->push($this->createPendingKasbon(20000, $this->customer->id));
        }

        $selectedIds = $txs->take(4)->pluck('id')->all();
        $unselectedId = $txs->last()->id;

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids'  => $selectedIds,
                'payment_method'   => 'deposit',
                'settlement_notes' => 'Potong saldo deposit',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(10000.0, (float) $this->customer->deposit_balance);
        $this->assertEquals(80000.0, (float) $this->customer->total_purchases);

        foreach ($selectedIds as $id) {
            $tx = Transaction::withoutGlobalScopes()->findOrFail($id);
            $this->assertEquals('paid', $tx->payment_status);
            $this->assertEquals('deposit', $tx->payment_method);
            $this->assertEquals('deposit', $tx->settlement_payment_method);
            $this->assertEquals(20000.0, (float) $tx->deposit_used);
            $this->assertEquals(0.0, (float) $tx->net_payable_amount);
        }

        $remainingTx = Transaction::withoutGlobalScopes()->findOrFail($unselectedId);
        $this->assertEquals('pending', $remainingTx->payment_status);

        // Tidak boleh mencatat CashFlow baru agar tidak double-counting dengan kas saat deposit disetor
        $this->assertEquals(0, CashFlow::count());
        $this->assertEquals(4, CustomerDepositLog::where('type', 'debit')->count());
    }

    public function test_voiding_kasbon_settled_by_deposit_restores_deposit_without_cash_refund(): void
    {
        $tx = $this->createPendingKasbon(20000, $this->customer->id);

        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ])
            ->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(70000.0, (float) $this->customer->deposit_balance);

        $this->actingAs($this->admin);
        app(BranchContext::class)->setBranchId($this->branch->id);
        $tx->refresh();

        app(AdminTransactionService::class)->deleteTransaction($tx, 'Batal order');

        $this->customer->refresh();
        $this->assertEquals(90000.0, (float) $this->customer->deposit_balance);
        $this->assertEquals(0, CashFlow::where('flow_type', 'cash_refund')->count());
        $this->assertEquals(1, CustomerDepositLog::where('type', 'void_restore')->count());
    }

    public function test_cash_bulk_settlement_with_overpayment_saves_excess_to_deposit_and_updates_drawer(): void
    {
        $tx1 = $this->createPendingKasbon(20000, $this->customer->id);
        $tx2 = $this->createPendingKasbon(20000, $this->customer->id);

        $response = $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids'  => [$tx1->id, $tx2->id],
                'payment_method'   => 'cash',
                'deposit_amount'   => 10000,
                'settlement_notes' => 'Bayar 50rb cash',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(100000.0, (float) $this->customer->deposit_balance);

        $this->assertEquals(
            40000.0,
            (float) CashFlow::where('flow_type', 'debt_settlement')
                ->where('payment_method', 'cash')
                ->where('affects_drawer', 1)
                ->sum('amount')
        );
        $this->assertEquals(
            10000.0,
            (float) CashFlow::where('flow_type', 'customer_deposit')
                ->where('payment_method', 'cash')
                ->where('affects_drawer', 1)
                ->sum('amount')
        );
        $this->assertEquals(1, CustomerDepositLog::where('type', 'credit')->where('amount', 10000)->count());

        app(BranchContext::class)->setBranchId($this->branch->id);
        $dashboard = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(40000.0, (float) $dashboard['pelunasanCashAmount']);
        $this->assertEquals(10000.0, (float) $dashboard['depositCashAmount']);
        $this->assertEquals(50000.0, (float) $dashboard['myNetCashToday']);

        $todayDate = now('Asia/Jakarta')->toDateString();
        $closing = app(CashierClosingService::class)->closeDailyShift([
            'business_id'         => $this->business->id,
            'branch_id'           => $this->branch->id,
            'user_id'             => $this->kasir->id,
            'date'                => $todayDate,
            'actual_cash_counted' => 50000,
            'closed_by'           => $this->kasir->id,
        ]);
        $this->assertEquals(40000.0, (float) $closing->cash_debt_collection);
        $this->assertEquals(50000.0, (float) $closing->expected_drawer_cash);

        $acsBranch = collect(app(AcsCashDrawerSyncService::class)->getBranchDrawerSummary($todayDate, $this->branch->code))
            ->firstWhere('branch_id', $this->branch->id);
        $this->assertIsArray($acsBranch);
        $this->assertEquals(40000.0, (float) $acsBranch['total_cash_debt_collection']);
        $this->assertEquals(10000.0, (float) $acsBranch['total_cash_deposits']);
        $this->assertEquals(50000.0, (float) $acsBranch['expected_drawer_cash']);

        $adminDashService = app(AdminDashboardService::class);
        $adminKasir = collect($adminDashService->getCashierCashOnHandData($this->business->id, now('Asia/Jakarta')))
            ->firstWhere('user_id', $this->kasir->id);
        $this->assertIsArray($adminKasir);
        $this->assertEquals(40000.0, (float) $adminKasir['pelunasan_cash']);
        $this->assertEquals(10000.0, (float) $adminKasir['deposit_cash']);
        $this->assertEquals(50000.0, (float) $adminKasir['physical_drawer']);
        $this->assertCount(2, $adminDashService->getSettlementAuditLogs($this->business->id, now('Asia/Jakarta')));
    }

    public function test_non_cash_transfer_settlement_with_overpayment_requires_single_proof_and_saves_deposit(): void
    {
        $tx1 = $this->createPendingKasbon(25000, $this->customer->id);
        $tx2 = $this->createPendingKasbon(25000, $this->customer->id);

        // Tanpa bukti transfer harus gagal validasi
        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx1->id, $tx2->id],
                'payment_method'  => 'transfer-bank',
                'deposit_amount'  => 50000,
            ])
            ->assertSessionHasErrors('payment_proof');

        // Dengan 1 bukti transfer untuk pelunasan + kelebihan deposit
        $proof = UploadedFile::fake()->image('bukti-tf.jpg');

        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx1->id, $tx2->id],
                'payment_method'  => 'transfer-bank',
                'deposit_amount'  => 50000,
                'payment_proof'   => $proof,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $tx1->refresh();
        $tx2->refresh();
        $this->assertNotEmpty($tx1->payment_proof);
        $this->assertEquals($tx1->payment_proof, $tx2->payment_proof);

        // Bersihkan file dummy yang diupload ke public/uploads/payments
        ProofUploadService::deleteProof($tx1->payment_proof);

        $this->customer->refresh();
        $this->assertEquals(140000.0, (float) $this->customer->deposit_balance);

        $this->assertEquals(
            50000.0,
            (float) CashFlow::where('flow_type', 'debt_settlement')
                ->where('payment_method', 'transfer-bank')
                ->where('affects_drawer', 0)
                ->sum('amount')
        );
        $this->assertEquals(
            50000.0,
            (float) CashFlow::where('flow_type', 'customer_deposit')
                ->where('payment_method', 'transfer-bank')
                ->where('affects_drawer', 0)
                ->sum('amount')
        );
    }

    public function test_dashboard_total_kasbon_uses_effective_payable_when_partial_deposit_used_at_pos(): void
    {
        $tx = $this->createPendingKasbon(50000, $this->customer->id, 20000, 30000);

        $this->actingAs($this->kasir);
        app(BranchContext::class)->setBranchId($this->branch->id);

        $dashboard = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(30000.0, (float) $dashboard['totalKasbonAmount']);

        $this->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ])
            ->assertSessionHas('success');

        $tx->refresh();
        $this->customer->refresh();
        $this->assertEquals(50000.0, (float) $tx->deposit_used);
        $this->assertEquals(0.0, (float) $tx->net_payable_amount);
        $this->assertEquals(60000.0, (float) $this->customer->deposit_balance);
    }

    public function test_walk_in_customer_without_customer_id_cannot_use_or_add_deposit(): void
    {
        $tx = $this->createPendingKasbon(20000, null);

        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ])
            ->assertSessionHas('error');

        $this->actingAs($this->kasir)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('kasir.transactions.bulkMarkAsPaid'), [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'cash',
                'deposit_amount'  => 5000,
            ])
            ->assertSessionHas('error');

        $tx->refresh();
        $this->assertEquals('pending', $tx->payment_status);
    }
}
