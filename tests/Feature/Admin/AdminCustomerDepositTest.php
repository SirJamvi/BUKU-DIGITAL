<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\BranchMonthlyClosing;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\Accounting\CustomerDepositService;
use App\Services\Acs\AcsCashDrawerSyncService;
use App\Services\Admin\DashboardService as AdminDashboardService;
use App\Services\Kasir\CashierClosingService;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerDepositTest extends TestCase
{
    use RefreshDatabase;

    protected Business $business;
    protected Branch $branch;
    protected User $admin;
    protected User $kasir;
    protected Customer $customer;
    protected ExpenseCategory $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name'      => 'Owner Admin',
            'email'     => 'owner-dep@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $this->business = Business::create([
            'name'     => 'Adin Crystal Admin Dep Test',
            'owner_id' => $this->admin->id,
        ]);
        $this->admin->update(['business_id' => $this->business->id]);

        $this->branch = Branch::create([
            'business_id' => $this->business->id,
            'name'        => 'Cabang Pusat',
            'code'        => 'CB-ADM-01',
            'is_main'     => true,
            'is_active'   => true,
            'manager_id'  => $this->admin->id,
        ]);
        $this->admin->update(['branch_id' => $this->branch->id]);
        $this->admin->syncBranches([$this->branch->id], $this->branch->id);

        $this->kasir = User::create([
            'business_id' => $this->business->id,
            'branch_id'   => $this->branch->id,
            'name'        => 'Kasir Shift',
            'email'       => 'kasir-dep@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $this->kasir->syncBranches([$this->branch->id], $this->branch->id);

        $this->customer = Customer::create([
            'business_id'     => $this->business->id,
            'name'            => 'Efan Pelanggan',
            'phone'           => '081234567899',
            'join_date'       => now()->toDateString(),
            'deposit_balance' => 0,
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

    public function test_admin_void_cash_deposit_creates_contra_entry_and_zeros_cashier_drawer_across_services(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            50000,
            'cash',
            null,
            'Salah input dobel oleh kasir',
            $this->kasir->id
        );

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $log->id), [
                'reason' => 'Kasir salah input 2 kali',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->customer->refresh();
        $log->refresh();

        $this->assertEquals(0.0, (float) $this->customer->deposit_balance);
        $this->assertNotNull($log->reversed_at);
        $this->assertEquals($this->admin->id, (int) $log->reversed_by);
        $this->assertEquals('Kasir salah input 2 kali', $log->reversal_reason);
        $this->assertNotNull($log->reversed_by_log_id);

        $contraLog = CustomerDepositLog::withoutGlobalScopes()->findOrFail($log->reversed_by_log_id);
        $this->assertEquals('refund', $contraLog->type);
        $this->assertEquals($log->id, (int) $contraLog->reverses_log_id);
        $this->assertEquals(50000.0, (float) $contraLog->balance_before);
        $this->assertEquals(0.0, (float) $contraLog->balance_after);

        // Zero Hard-Delete: CashFlow asal + CashFlow pembalik keduanya tetap ada
        $this->assertEquals(1, CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->where('flow_type', 'customer_deposit')->count());
        $contraCf = CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->where('flow_type', 'deposit_refund')->firstOrFail();
        $this->assertEquals($this->kasir->id, (int) $contraCf->created_by);
        $this->assertEquals(1, (int) $contraCf->affects_drawer);
        $this->assertEquals(50000.0, (float) $contraCf->amount);

        // Semua 4 service keuangan laci harus kembali 0
        app(BranchContext::class)->setBranchId($this->branch->id);
        $kasirDash = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(0.0, (float) $kasirDash['myNetCashToday']);

        $today = now('Asia/Jakarta')->toDateString();
        $closing = app(CashierClosingService::class)->closeDailyShift([
            'business_id'         => $this->business->id,
            'branch_id'           => $this->branch->id,
            'user_id'             => $this->kasir->id,
            'date'                => $today,
            'actual_cash_counted' => 0,
            'closed_by'           => $this->kasir->id,
        ]);
        $this->assertEquals(0.0, (float) $closing->expected_drawer_cash);

        $acsBranch = collect(app(AcsCashDrawerSyncService::class)->getBranchDrawerSummary($today, $this->branch->code))
            ->firstWhere('branch_id', $this->branch->id);
        $this->assertEquals(0.0, (float) $acsBranch['expected_drawer_cash']);

        $adminKasir = collect(app(AdminDashboardService::class)->getCashierCashOnHandData($this->business->id, now('Asia/Jakarta')))
            ->firstWhere('user_id', $this->kasir->id);
        $this->assertEquals(0.0, (float) $adminKasir['physical_drawer']);
    }

    public function test_admin_void_non_cash_deposit_preserves_affects_drawer_zero(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            100000,
            'transfer-bank',
            null,
            'Transfer dobel',
            $this->kasir->id
        );

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $log->id), [
                'reason' => 'Transfer nyasar / batal order',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(0.0, (float) $this->customer->deposit_balance);

        $contraCf = CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->where('flow_type', 'deposit_refund')->firstOrFail();
        $this->assertEquals(0, (int) $contraCf->affects_drawer);
        $this->assertEquals('owner', $contraCf->funding_source);
        $this->assertEquals('transfer-bank', $contraCf->payment_method);
    }

    public function test_double_void_and_non_credit_void_are_blocked(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            25000,
            'cash',
            null,
            'Setoran uji',
            $this->kasir->id
        );

        $contra = CustomerDepositService::reverseDeposit($this->business->id, $log->id, 'Void pertama', $this->admin->id);

        // Void ke-2 pada log yang sama harus ditolak
        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $log->id), [
                'reason' => 'Void kedua',
            ])
            ->assertSessionHas('error');

        // Void pada log bertipe refund harus ditolak
        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $contra->id), [
                'reason' => 'Coba void contra',
            ])
            ->assertSessionHas('error');

        $this->assertEquals(1, CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->where('flow_type', 'deposit_refund')->count());
    }

    public function test_void_deposit_blocked_when_balance_already_consumed_to_prevent_negative_balance(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            50000,
            'cash',
            null,
            'Deposit awal 50rb',
            $this->kasir->id
        );

        $tx = Transaction::create([
            'business_id'        => $this->business->id,
            'branch_id'          => $this->branch->id,
            'type'               => 'sale',
            'customer_id'        => $this->customer->id,
            'total_amount'       => 20000,
            'deposit_used'       => 20000,
            'net_payable_amount' => 0,
            'payment_method'     => 'deposit',
            'payment_status'     => 'paid',
            'status'             => 'completed',
            'transaction_date'   => now(),
            'created_by'         => $this->kasir->id,
        ]);

        CustomerDepositService::useDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            20000,
            $tx->id,
            $this->kasir->id
        );

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $log->id), [
                'reason' => 'Coba void padahal sudah terpakai 20k',
            ])
            ->assertSessionHas('error');

        $this->customer->refresh();
        $log->refresh();
        $this->assertEquals(30000.0, (float) $this->customer->deposit_balance);
        $this->assertNull($log->reversed_at);
        $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->where('flow_type', 'deposit_refund')->count());
    }

    public function test_one_step_atomic_correction_updates_balance_and_cashier_drawer_cleanly(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            150000,
            'cash',
            null,
            'Salah ketik kelebihan angka nol',
            $this->kasir->id
        );

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.correct', $log->id), [
                'new_amount'         => 15000,
                'new_payment_method' => 'cash',
                'reason'             => 'Harusnya Rp 15.000 bukan Rp 150.000',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->customer->refresh();
        $log->refresh();

        $this->assertEquals(15000.0, (float) $this->customer->deposit_balance);
        $this->assertNotNull($log->reversed_at);
        $this->assertEquals(1, CustomerDepositLog::withoutGlobalScopes()->where('business_id', $this->business->id)->where('type', 'credit')->whereNull('reversed_at')->count());
        $activityLog = UserActivityLog::where('business_id', $this->business->id)->where('action', 'correct_customer_deposit')->first();
        $this->assertNotNull($activityLog);
        $this->assertIsString($activityLog->details);

        app(BranchContext::class)->setBranchId($this->branch->id);
        $kasirDash = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(15000.0, (float) $kasirDash['myNetCashToday']);
    }

    public function test_admin_opening_balance_deposit_adds_balance_without_cashflow_or_drawer_impact(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.store'), [
                'customer_id'  => $this->customer->id,
                'branch_id'    => $this->branch->id,
                'deposit_mode' => 'opening_balance',
                'amount'       => 15000,
                'notes'        => 'Migrasi catatan WA kelebihan bayar kemarin',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->customer->refresh();
        $this->assertEquals(15000.0, (float) $this->customer->deposit_balance);
        $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $this->business->id)->count());

        $log = CustomerDepositLog::withoutGlobalScopes()->where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertEquals('opening_balance', $log->payment_method);

        app(BranchContext::class)->setBranchId($this->branch->id);
        $kasirDash = app(KasirDashboardService::class)->getDashboardData($this->kasir->id);
        $this->assertEquals(0.0, (float) $kasirDash['myNetCashToday']);
    }

    public function test_void_and_correction_are_blocked_when_accounting_period_is_closed(): void
    {
        $log = CustomerDepositService::addDeposit(
            $this->business->id,
            $this->branch->id,
            $this->customer->id,
            30000,
            'cash',
            null,
            'Deposit sebelum tutup buku',
            $this->kasir->id
        );

        BranchMonthlyClosing::create([
            'business_id'  => $this->business->id,
            'branch_id'    => $this->branch->id,
            'period_month' => (int) now()->format('m'),
            'period_year'  => (int) now()->format('Y'),
            'status'       => 'completed',
            'closed_by'    => $this->admin->id,
            'closed_at'    => now(),
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.reverse', $log->id), [
                'reason' => 'Coba void setelah closing',
            ])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->withSession(['active_branch_id' => $this->branch->id])
            ->post(route('admin.deposits.correct', $log->id), [
                'new_amount'         => 10000,
                'new_payment_method' => 'cash',
                'reason'             => 'Coba koreksi setelah closing',
            ])
            ->assertSessionHas('error');

        $this->customer->refresh();
        $log->refresh();
        $this->assertEquals(30000.0, (float) $this->customer->deposit_balance);
        $this->assertNull($log->reversed_at);
    }
}
