<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerDepositController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Requests\Admin\CorrectDepositRequest;
use App\Http\Requests\Admin\ReverseDepositRequest;
use App\Http\Requests\Admin\StoreAdminDepositRequest;
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
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

class VerifyAdminDepositCommand extends Command
{
    protected $signature = 'deposit:verify-admin';

    protected $description = 'Automated end-to-end verification (100% DB rollback) untuk fitur Admin Deposit, Saldo Awal, Void Contra-Entry & Koreksi 1 Langkah';

    /** @var array<int, array<int, string>> */
    protected array $rows = [];

    protected int $passed = 0;
    protected int $failed = 0;

    public function handle(): int
    {
        $this->info('=== MEMULAI VERIFIKASI FITUR ADMIN DEPOSIT & AUDIT FMCG (ROLLBACK MODE) ===');

        if (!Schema::hasColumn('customer_deposit_logs', 'reversed_at')) {
            $this->error('Kolom reversed_at belum ada di customer_deposit_logs. Jalankan `php artisan migrate --force` terlebih dahulu.');
            return 1;
        }

        $this->runScenario('D01', 'Void Deposit Cash Kasir -> Contra-Entry & 4 Service Laci kembali Rp 0', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                50000,
                'cash',
                null,
                'Kasir salah input dobel',
                $ctx['kasir']->id
            );

            $res = $this->submitReverse($ctx, $log->id, 'Kasir salah input 2x');
            $this->assertTrue($res['session']->has('success'), 'Void deposit cash harus sukses.');

            $ctx['customer']->refresh();
            $log->refresh();

            $this->assertFloatEquals(0.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus kembali 0.');
            $this->assertTrue(!empty($log->reversed_at), 'reversed_at harus terisi.');
            $this->assertEquals($ctx['admin']->id, (int) $log->reversed_by, 'reversed_by harus ID Admin.');

            $contraCf = CashFlow::withoutGlobalScopes()
                ->where('business_id', $ctx['business']->id)
                ->where('flow_type', 'deposit_refund')
                ->first();
            $this->assertTrue($contraCf !== null, 'Harus ada CashFlow contra-entry deposit_refund.');
            $this->assertEquals($ctx['kasir']->id, (int) $contraCf->created_by, 'Contra-entry harus memotong laci kasir asal.');
            $this->assertEquals(1, (int) $contraCf->affects_drawer, 'Contra-entry cash harus affects_drawer=1.');

            $kasirDash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(0.0, (float) $kasirDash['myNetCashToday'], 'KasirDashboardService myNetCashToday harus kembali Rp 0.');

            $today = now('Asia/Jakarta')->toDateString();
            $closing = app(CashierClosingService::class)->closeDailyShift([
                'business_id'         => $ctx['business']->id,
                'branch_id'           => $ctx['branch']->id,
                'user_id'             => $ctx['kasir']->id,
                'date'                => $today,
                'actual_cash_counted' => 0,
                'closed_by'           => $ctx['kasir']->id,
            ]);
            $this->assertFloatEquals(0.0, (float) $closing->expected_drawer_cash, 'CashierClosingService expected_drawer_cash harus 0.');

            $acsBranch = collect(app(AcsCashDrawerSyncService::class)->getBranchDrawerSummary($today, $ctx['branch']->code))
                ->firstWhere('branch_id', $ctx['branch']->id);
            $this->assertFloatEquals(0.0, (float) $acsBranch['expected_drawer_cash'], 'ACS expected_drawer_cash harus 0.');

            $adminKasir = collect(app(AdminDashboardService::class)->getCashierCashOnHandData($ctx['business']->id, now('Asia/Jakarta')))
                ->firstWhere('user_id', $ctx['kasir']->id);
            $this->assertFloatEquals(0.0, (float) $adminKasir['physical_drawer'], 'Admin physical_drawer harus 0.');

            return 'Saldo Rp 0, Zero Hard-Delete utuh, 4 service laci kasir kembali Rp 0';
        });

        $this->runScenario('D02', 'Void Deposit Non-Cash (Transfer) menjaga affects_drawer = 0', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                100000,
                'transfer-bank',
                null,
                'Transfer salah input',
                $ctx['kasir']->id
            );

            $res = $this->submitReverse($ctx, $log->id, 'Transfer salah pelanggan');
            $this->assertTrue($res['session']->has('success'), 'Void transfer harus sukses.');

            $contraCf = CashFlow::withoutGlobalScopes()
                ->where('business_id', $ctx['business']->id)
                ->where('flow_type', 'deposit_refund')
                ->first();
            $this->assertTrue($contraCf !== null, 'Contra CashFlow harus dibuat.');
            $this->assertEquals(0, (int) $contraCf->affects_drawer, 'Non-cash tidak boleh memotong laci kasir.');
            $this->assertEquals('owner', $contraCf->funding_source, 'Funding source harus owner.');

            return 'Saldo Rp 0, deposit_refund transfer dengan affects_drawer=0';
        });

        $this->runScenario('D03', 'Blokir Double-Void dan Void Mutasi Non-Credit', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                25000,
                'cash',
                null,
                'Setoran uji',
                $ctx['kasir']->id
            );

            $contra = CustomerDepositService::reverseDeposit($ctx['business']->id, $log->id, 'Void ke-1', $ctx['admin']->id);

            $resDup = $this->submitReverse($ctx, $log->id, 'Coba void ke-2');
            $this->assertTrue($resDup['session']->has('error'), 'Void ke-2 pada log sama harus ditolak.');

            $resContra = $this->submitReverse($ctx, $contra->id, 'Coba void log refund');
            $this->assertTrue($resContra['session']->has('error'), 'Void pada log refund harus ditolak.');

            return 'Double-void dan void non-credit ditolak aman';
        });

        $this->runScenario('D04', 'Anti Saldo Minus: Blokir Void jika Deposit sudah Terpakai Belanja', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                50000,
                'cash',
                null,
                'Deposit awal 50k',
                $ctx['kasir']->id
            );

            $tx = Transaction::withoutGlobalScopes()->create([
                'business_id'        => $ctx['business']->id,
                'branch_id'          => $ctx['branch']->id,
                'type'               => 'sale',
                'customer_id'        => $ctx['customer']->id,
                'total_amount'       => 20000,
                'deposit_used'       => 20000,
                'net_payable_amount' => 0,
                'payment_method'     => 'deposit',
                'payment_status'     => 'paid',
                'status'             => 'completed',
                'transaction_date'   => now(),
                'created_by'         => $ctx['kasir']->id,
            ]);

            CustomerDepositService::useDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                20000,
                $tx->id,
                $ctx['kasir']->id
            );

            $res = $this->submitReverse($ctx, $log->id, 'Coba void 50k saat sisa 30k');
            $this->assertTrue($res['session']->has('error'), 'Void melebihi sisa saldo harus ditolak.');

            $ctx['customer']->refresh();
            $log->refresh();
            $this->assertFloatEquals(30000.0, (float) $ctx['customer']->deposit_balance, 'Sisa saldo harus utuh 30.000.');
            $this->assertTrue(empty($log->reversed_at), 'Log awal tidak boleh berubah.');

            return 'Diblokir aman (anti saldo minus), sisa saldo utuh Rp 30.000';
        });

        $this->runScenario('D05', 'Koreksi Nominal 1 Langkah Atomik (150k -> 15k) Sinkron Laci Kasir', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                150000,
                'cash',
                null,
                'Kelebihan ketik angka 0',
                $ctx['kasir']->id
            );

            $res = $this->submitCorrect($ctx, $log->id, 15000, 'cash', 'Koreksi harusnya 15.000');
            $this->assertTrue($res['session']->has('success'), 'Koreksi 1 langkah harus sukses.');

            $ctx['customer']->refresh();
            $log->refresh();

            $this->assertFloatEquals(15000.0, (float) $ctx['customer']->deposit_balance, 'Saldo setelah koreksi harus 15.000.');
            $this->assertTrue(!empty($log->reversed_at), 'Log lama harus berstatus reversed.');
            $activityLog = UserActivityLog::withoutGlobalScopes()
                ->where('business_id', $ctx['business']->id)
                ->where('action', 'correct_customer_deposit')
                ->first();
            $this->assertTrue($activityLog !== null, 'Harus mencatat UserActivityLog correct_customer_deposit.');
            $this->assertTrue(is_string($activityLog->details), 'UserActivityLog details harus berupa string.');

            $kasirDash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(15000.0, (float) $kasirDash['myNetCashToday'], 'Laci kasir harus pas Rp 15.000.');

            return '150k di-void + 15k tercatat, saldo & laci kasir pas Rp 15.000';
        });

        $this->runScenario('D06', 'Admin Input Saldo Awal (Lampu Merah 5k, Efan 15k, Pa Anwar 1k) Tanpa Sentuh Laci', function (array $ctx) {
            $res = $this->submitStore($ctx, [
                'customer_id'  => $ctx['customer']->id,
                'branch_id'    => $ctx['branch']->id,
                'deposit_mode' => 'opening_balance',
                'amount'       => 15000,
                'notes'        => 'Catatan kasir lama',
            ]);
            $this->assertTrue($res['session']->has('success'), 'Input saldo awal harus berhasil.');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(15000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus jadi 15.000.');
            $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->count(), 'Saldo awal tidak boleh membuat CashFlow.');

            $kasirDash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(0.0, (float) $kasirDash['myNetCashToday'], 'Laci kasir hari ini harus tetap Rp 0.');

            return 'Saldo awal +Rp 15.000 tersimpan, 0 CashFlow, laci kasir Rp 0';
        });

        $this->runScenario('D07', 'Period Closing Lock: Blokir Void & Koreksi saat Periode Sudah Tutup Buku', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                30000,
                'cash',
                null,
                'Deposit bulan berjalan',
                $ctx['kasir']->id
            );

            BranchMonthlyClosing::withoutGlobalScopes()->create([
                'business_id'  => $ctx['business']->id,
                'branch_id'    => $ctx['branch']->id,
                'period_month' => (int) now()->format('m'),
                'period_year'  => (int) now()->format('Y'),
                'status'       => 'completed',
                'closed_by'    => $ctx['admin']->id,
                'closed_at'    => now(),
            ]);

            $resRev = $this->submitReverse($ctx, $log->id, 'Coba void setelah closing');
            $this->assertTrue($resRev['session']->has('error'), 'Void setelah tutup buku harus diblokir.');

            $resCor = $this->submitCorrect($ctx, $log->id, 10000, 'cash', 'Coba koreksi setelah closing');
            $this->assertTrue($resCor['session']->has('error'), 'Koreksi setelah tutup buku harus diblokir.');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(30000.0, (float) $ctx['customer']->deposit_balance, 'Saldo tidak boleh berubah.');

            return 'Void & Koreksi ditolak aman oleh AccountingPeriodGuard';
        });

        $this->runScenario('D08', 'Render Admin Pusat Deposit, Detail Pelanggan & Dashboard (UserActivityLog)', function (array $ctx) {
            CustomerDepositService::addOpeningDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                40000,
                'Setoran untuk cek render',
                $ctx['admin']->id
            );

            // Simulasikan pula baris UserActivityLog lama ber-format array agar Blade terbukti tahan banting
            UserActivityLog::create([
                'business_id' => $ctx['business']->id,
                'branch_id'   => $ctx['branch']->id,
                'user_id'     => $ctx['admin']->id,
                'action'      => 'legacy_array_log',
                'module'      => 'customer_deposit',
                'details'     => ['legacy' => 'array_payload'],
                'ip_address'  => '127.0.0.1',
            ]);

            View::share('errors', new ViewErrorBag());
            View::share('available_branches', collect([$ctx['branch']]));

            $req = Request::create('/admin/deposits', 'GET');
            $req->setUserResolver(fn () => Auth::user());
            $htmlIndex = app(CustomerDepositController::class)->index($req)->render();

            $this->assertTrue(str_contains($htmlIndex, 'Pusat Pengelolaan &amp; Koreksi Deposit Pelanggan') || str_contains($htmlIndex, 'Pusat Pengelolaan & Koreksi Deposit Pelanggan'), 'View admin.deposits.index harus ter-render.');
            $this->assertTrue(str_contains($htmlIndex, 'modalReverseDeposit'), 'Modal Void harus ikut ter-render.');
            $this->assertTrue(str_contains($htmlIndex, 'modalCorrectDeposit'), 'Modal Koreksi harus ikut ter-render.');

            $htmlShow = app(CustomerController::class)->show($ctx['customer']->fresh())->render();
            $this->assertTrue(str_contains($htmlShow, 'Saldo Deposit Pelanggan'), 'Detail pelanggan harus menampilkan card Saldo Deposit.');

            $dashReq = Request::create('/admin/dashboard', 'GET');
            $dashReq->setUserResolver(fn () => Auth::user());
            $htmlDash = app(AdminDashboardController::class)->index($dashReq)->render();
            $this->assertTrue(str_contains($htmlDash, 'legacy_array_log') || str_contains($htmlDash, 'add_opening_deposit'), 'Admin dashboard harus ter-render tanpa TypeError mb_strwidth.');

            return 'View admin.deposits.index, customers.show & dashboard.index ter-render valid';
        });

        $this->runScenario('D09', 'Koreksi Nominal saat Deposit Terpakai Sebagian (30rb -> pakai 15rb -> koreksi jadi 15rb = saldo 0; tolak 10rb)', function (array $ctx) {
            $log = CustomerDepositService::addDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                30000,
                'cash',
                null,
                'Kasir double input menjadi Rp 30.000',
                $ctx['kasir']->id
            );

            $tx = Transaction::create([
                'business_id'        => $ctx['business']->id,
                'branch_id'          => $ctx['branch']->id,
                'type'               => 'sale',
                'customer_id'        => $ctx['customer']->id,
                'total_amount'       => 15000,
                'deposit_used'       => 15000,
                'net_payable_amount' => 0,
                'payment_method'     => 'deposit',
                'payment_status'     => 'paid',
                'status'             => 'completed',
                'transaction_date'   => now(),
                'created_by'         => $ctx['kasir']->id,
            ]);

            CustomerDepositService::useDeposit(
                $ctx['business']->id,
                $ctx['branch']->id,
                $ctx['customer']->id,
                15000,
                $tx->id,
                $ctx['kasir']->id
            );

            $resTooLow = $this->submitCorrect($ctx, $log->id, 10000, 'cash', 'Coba turun dibawah terpakai');
            $this->assertTrue($resTooLow['session']->has('error'), 'Koreksi 30rb -> 10rb saat terpakai 15rb harus ditolak (Anti Saldo Minus).');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(15000.0, (float) $ctx['customer']->deposit_balance, 'Saldo setelah koreksi ditolak harus tetap 15.000.');

            $resValid = $this->submitCorrect($ctx, $log->id, 15000, 'cash', 'Kasir double input 30rb seharusnya 15rb');
            $this->assertTrue($resValid['session']->has('success'), 'Koreksi 30rb -> 15rb saat terpakai 15rb harus sukses.');

            $ctx['customer']->refresh();
            $log->refresh();
            $this->assertFloatEquals(0.0, (float) $ctx['customer']->deposit_balance, 'Saldo akhir harus Rp 0.');
            $this->assertTrue(!empty($log->reversed_at), 'Log asal harus tertanda reversed.');

            $logs = CustomerDepositLog::withoutGlobalScopes()
                ->where('business_id', $ctx['business']->id)
                ->where('customer_id', $ctx['customer']->id)
                ->get();
            foreach ($logs as $item) {
                $this->assertTrue((float) $item->balance_before >= 0.0, 'balance_before tidak boleh minus.');
                $this->assertTrue((float) $item->balance_after >= 0.0, 'balance_after tidak boleh minus.');
            }

            $kasirDash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(15000.0, (float) $kasirDash['myNetCashToday'], 'Laci kasir harus menjadi Rp 15.000.');

            return 'Koreksi 10rb ditolak; koreksi 15rb sukses, saldo Rp 0, laci kasir Rp 15.000';
        });

        $this->newLine();
        $this->table(['Kode', 'Skenario Pengujian', 'Status', 'Detail'], $this->rows);
        $this->info(sprintf('Ringkasan: %d PASS | %d FAIL (Total %d skenario, 100%% DB rollback)', $this->passed, $this->failed, $this->passed + $this->failed));

        return $this->failed === 0 ? 0 : 1;
    }

    protected function runScenario(string $code, string $title, callable $callback): void
    {
        $baseLevel = DB::transactionLevel();
        $session = new SessionStore('admin_deposit_verify', new ArraySessionHandler(10));
        $session->start();
        app('redirect')->setSession($session);

        DB::beginTransaction();
        try {
            $ctx = $this->createFixtureContext($session);
            $detail = (string) $callback($ctx);
            $this->rows[] = [$code, $title, 'PASS', $detail];
            $this->passed++;
        } catch (\Throwable $e) {
            $this->rows[] = [
                $code,
                $title,
                'FAIL',
                $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ];
            $this->failed++;
        } finally {
            while (DB::transactionLevel() > $baseLevel) {
                DB::rollBack();
            }
            if (isset($ctx['business'])) {
                \App\Support\BranchCache::flush((int) $ctx['business']->id);
            }
        }
    }

    protected function createFixtureContext(SessionStore $session): array
    {
        $admin = User::withoutGlobalScopes()->create([
            'name'      => 'Admin Verif',
            'email'     => 'adm-' . Str::random(8) . '@verify.local',
            'password'  => 'secret123',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $business = Business::create([
            'name'     => 'Verify Admin Dep Biz ' . Str::random(5),
            'owner_id' => $admin->id,
        ]);
        $admin->update(['business_id' => $business->id]);

        $branch = Branch::create([
            'business_id' => $business->id,
            'name'        => 'Cabang Verif Admin',
            'code'        => 'CBVA' . random_int(100, 999),
            'is_main'     => true,
            'is_active'   => true,
            'manager_id'  => $admin->id,
        ]);
        $admin->update(['branch_id' => $branch->id]);
        $admin->syncBranches([$branch->id], $branch->id);

        $kasir = User::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Kasir Verif Admin',
            'email'       => 'ksr-' . Str::random(8) . '@verify.local',
            'password'    => 'secret123',
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $kasir->syncBranches([$branch->id], $branch->id);

        ExpenseCategory::withoutGlobalScopes()->create([
            'business_id'   => $business->id,
            'name'          => 'Pendapatan Verif',
            'type'          => 'income',
            'category_type' => 'Operasional',
            'is_cogs'       => false,
            'is_active'     => true,
            'created_by'    => $admin->id,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'business_id'     => $business->id,
            'name'            => 'Efan Verif',
            'phone'           => '0888' . random_int(1000000, 9999999),
            'join_date'       => now()->toDateString(),
            'status'          => 'active',
            'deposit_balance' => 0,
            'total_purchases' => 0,
            'created_by'      => $admin->id,
        ]);

        Auth::guard('web')->setUser($admin);
        Auth::shouldUse('web');
        app(BranchContext::class)->setBranch($branch->id, $business->id);
        $session->put('active_branch_id', $branch->id);
        $session->put('active_branch_name', $branch->name);

        return compact('admin', 'business', 'branch', 'kasir', 'customer', 'session');
    }

    /**
     * @return array{response: RedirectResponse, session: SessionStore}
     */
    protected function submitStore(array $ctx, array $data): array
    {
        $session = $ctx['session'];
        $session->forget(['success', 'error', 'warning', 'errors']);

        $request = Request::create('/admin/deposits', 'POST', $data);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());

        $formRequest = StoreAdminDepositRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        $response = app(CustomerDepositController::class)->store($formRequest);

        return ['response' => $response, 'session' => $session];
    }

    /**
     * @return array{response: RedirectResponse, session: SessionStore}
     */
    protected function submitReverse(array $ctx, int $logId, string $reason): array
    {
        $session = $ctx['session'];
        $session->forget(['success', 'error', 'warning', 'errors']);

        $request = Request::create("/admin/deposits/{$logId}/reverse", 'POST', ['reason' => $reason]);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());

        $formRequest = ReverseDepositRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        $response = app(CustomerDepositController::class)->reverse($formRequest, $logId);

        return ['response' => $response, 'session' => $session];
    }

    /**
     * @return array{response: RedirectResponse, session: SessionStore}
     */
    protected function submitCorrect(array $ctx, int $logId, float $newAmount, string $newMethod, string $reason): array
    {
        $session = $ctx['session'];
        $session->forget(['success', 'error', 'warning', 'errors']);

        $request = Request::create("/admin/deposits/{$logId}/correct", 'POST', [
            'new_amount'         => $newAmount,
            'new_payment_method' => $newMethod,
            'reason'             => $reason,
        ]);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());

        $formRequest = CorrectDepositRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'))->validateResolved();

        $response = app(CustomerDepositController::class)->correct($formRequest, $logId);

        return ['response' => $response, 'session' => $session];
    }

    protected function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . ' (expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . ')');
        }
    }

    protected function assertFloatEquals(float $expected, float $actual, string $message): void
    {
        if (abs($expected - $actual) > 0.01) {
            throw new \RuntimeException($message . " (expected: {$expected}, actual: {$actual})");
        }
    }
}
