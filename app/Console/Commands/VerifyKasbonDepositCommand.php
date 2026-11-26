<?php

namespace App\Console\Commands;

use App\Http\Controllers\Kasir\DashboardController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\TransactionController;
use App\Http\Requests\Kasir\BulkMarkAsPaidRequest;
use App\Http\Requests\Kasir\CompleteDeliveryTripRequest;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashFlow;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Acs\AcsCashDrawerSyncService;
use App\Services\Admin\DashboardService as AdminDashboardService;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\CashierClosingService;
use App\Services\Kasir\DashboardService as KasirDashboardService;
use App\Services\ProofUploadService;
use App\Support\BranchContext;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class VerifyKasbonDepositCommand extends Command
{
    /**
     * Signature console command.
     */
    protected $signature = 'kasbon:verify';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Automated end-to-end verification (100% DB rollback) untuk sistem Deposit Kasbon Aktif & FIFO Pengiriman Driver (Kasus Efan)';

    /**
     * Daftar hasil pengujian.
     *
     * @var array<int, array<int, string>>
     */
    protected array $rows = [];

    protected int $passed = 0;
    protected int $failed = 0;

    /**
     * Jalankan seluruh 12 skenario pengujian terisolasi.
     */
    public function handle(): int
    {
        $this->info('=== MEMULAI VERIFIKASI SISTEM DEPOSIT KASBON & FIFO PENGIRIMAN (ROLLBACK MODE) ===');

        $this->runScenario('S01', 'Kasbon Deposit: Blokir jika total nota (100k) > saldo (90k)', function (array $ctx) {
            $txs = collect();
            for ($i = 0; $i < 5; $i++) {
                $txs->push($this->createKasbon($ctx, 20000, $ctx['customer']->id));
            }

            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => $txs->pluck('id')->all(),
                'payment_method'  => 'deposit',
            ]);

            $this->assertTrue($res['session']->has('error'), 'Harus menghasilkan flash error saat saldo deposit kurang.');
            $ctx['customer']->refresh();
            $this->assertFloatEquals(90000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit tidak boleh terpotong.');
            $pendingCount = Transaction::withoutGlobalScopes()
                ->where('business_id', $ctx['business']->id)
                ->where('payment_status', 'pending')
                ->count();
            $this->assertEquals(5, $pendingCount, 'Semua 5 nota harus tetap pending.');
            $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->count(), 'Tidak boleh ada CashFlow.');

            return 'Diblokir aman, 5 nota tetap pending, saldo utuh Rp 90.000';
        });

        $this->runScenario('S02', 'Kasbon Deposit: Lunasi 4 dari 5 nota (80k <= 90k), sisa 10k', function (array $ctx) {
            $txs = collect();
            for ($i = 0; $i < 5; $i++) {
                $txs->push($this->createKasbon($ctx, 20000, $ctx['customer']->id));
            }

            $selectedIds = $txs->take(4)->pluck('id')->all();
            $unselectedId = $txs->last()->id;

            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids'  => $selectedIds,
                'payment_method'   => 'deposit',
                'settlement_notes' => 'Potong saldo deposit',
            ]);

            $this->assertTrue($res['session']->has('success'), 'Pelunasan 4 nota harus berhasil.');
            $ctx['customer']->refresh();
            $this->assertFloatEquals(10000.0, (float) $ctx['customer']->deposit_balance, 'Sisa saldo deposit harus Rp 10.000.');
            $this->assertFloatEquals(80000.0, (float) $ctx['customer']->total_purchases, 'Total belanja harus Rp 80.000.');

            foreach ($selectedIds as $id) {
                $tx = Transaction::withoutGlobalScopes()->findOrFail($id);
                $this->assertEquals('paid', $tx->payment_status, 'Nota terpilih harus paid.');
                $this->assertEquals('deposit', $tx->payment_method, 'Metode bayar harus deposit.');
                $this->assertEquals('deposit', $tx->settlement_payment_method, 'Settlement method harus deposit.');
                $this->assertFloatEquals(20000.0, (float) $tx->deposit_used, 'deposit_used harus 20.000.');
                $this->assertFloatEquals(0.0, (float) $tx->net_payable_amount, 'net_payable_amount harus 0.');
            }

            $remain = Transaction::withoutGlobalScopes()->findOrFail($unselectedId);
            $this->assertEquals('pending', $remain->payment_status, 'Nota ke-5 harus tetap pending.');
            $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->count(), 'Tidak boleh mencatat CashFlow ganda.');
            $this->assertEquals(4, CustomerDepositLog::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->where('type', 'debit')->count(), 'Harus ada 4 log debit.');

            return '4 nota lunas, 1 tetap kasbon, sisa deposit Rp 10.000, 0 CashFlow';
        });

        $this->runScenario('S03', 'Void Proteksi: Nota lunas deposit di-Void -> saldo pulih tanpa cash refund', function (array $ctx) {
            $tx = $this->createKasbon($ctx, 20000, $ctx['customer']->id);

            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ]);
            $this->assertTrue($res['session']->has('success'), 'Pelunasan deposit harus sukses.');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(70000.0, (float) $ctx['customer']->deposit_balance, 'Saldo setelah potong harus 70.000.');

            Auth::guard('web')->setUser($ctx['admin']);
            app(BranchContext::class)->setBranch($ctx['branch']->id, $ctx['business']->id);

            app(AdminTransactionService::class)->deleteTransaction($tx->fresh(), 'Batal order verifikasi');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(90000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus kembali ke 90.000 setelah void.');
            $this->assertEquals(0, CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->where('flow_type', 'cash_refund')->count(), 'Tidak boleh ada cash_refund.');
            $this->assertEquals(1, CustomerDepositLog::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->where('type', 'void_restore')->count(), 'Harus ada 1 void_restore log.');

            return 'Saldo pulih ke Rp 90.000, 1 log void_restore, 0 cash_refund';
        });

        $this->runScenario('S04', 'Kasbon Cash + Kelebihan Bayar Deposit (40k + 10k = 50k laci)', function (array $ctx) {
            $tx1 = $this->createKasbon($ctx, 20000, $ctx['customer']->id);
            $tx2 = $this->createKasbon($ctx, 20000, $ctx['customer']->id);

            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids'  => [$tx1->id, $tx2->id],
                'payment_method'   => 'cash',
                'deposit_amount'   => 10000,
                'settlement_notes' => 'Bayar 50rb cash',
            ]);
            $this->assertTrue($res['session']->has('success'), 'Pelunasan cash + kelebihan deposit harus sukses.');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(100000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus jadi 100.000.');

            $dash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(40000.0, (float) $dash['pelunasanCashAmount'], 'pelunasanCashAmount harus 40.000.');
            $this->assertFloatEquals(10000.0, (float) $dash['depositCashAmount'], 'depositCashAmount harus 10.000.');
            $this->assertFloatEquals(50000.0, (float) $dash['myNetCashToday'], 'myNetCashToday harus 50.000.');

            $todayDate = now('Asia/Jakarta')->toDateString();
            $closing = app(CashierClosingService::class)->closeDailyShift([
                'business_id'         => $ctx['business']->id,
                'branch_id'           => $ctx['branch']->id,
                'user_id'             => $ctx['kasir']->id,
                'date'                => $todayDate,
                'actual_cash_counted' => 50000,
                'closed_by'           => $ctx['kasir']->id,
            ]);
            $this->assertFloatEquals(40000.0, (float) $closing->cash_debt_collection, 'CashierClosingService cash_debt_collection harus 40.000.');
            $this->assertFloatEquals(50000.0, (float) $closing->expected_drawer_cash, 'CashierClosingService expected_drawer_cash harus 50.000.');
            $this->assertFloatEquals(0.0, (float) $closing->variance, 'CashierClosingService variance harus 0.');

            $acsSummary = app(AcsCashDrawerSyncService::class)->getBranchDrawerSummary($todayDate, $ctx['branch']->code);
            $acsBranch = collect($acsSummary)->firstWhere('branch_id', $ctx['branch']->id);
            $this->assertTrue(is_array($acsBranch), 'AcsCashDrawerSyncService harus mengembalikan ringkasan cabang.');
            $this->assertFloatEquals(40000.0, (float) $acsBranch['total_cash_debt_collection'], 'ACS total_cash_debt_collection harus 40.000.');
            $this->assertFloatEquals(10000.0, (float) $acsBranch['total_cash_deposits'], 'ACS total_cash_deposits harus 10.000.');
            $this->assertFloatEquals(50000.0, (float) $acsBranch['expected_drawer_cash'], 'ACS expected_drawer_cash harus 50.000.');

            $adminDashService = app(AdminDashboardService::class);
            $adminCashiers = $adminDashService->getCashierCashOnHandData($ctx['business']->id, now('Asia/Jakarta'));
            $adminKasir = collect($adminCashiers)->firstWhere('user_id', $ctx['kasir']->id);
            $this->assertTrue(is_array($adminKasir), 'Admin DashboardService harus memuat metrik kasir.');
            $this->assertFloatEquals(40000.0, (float) $adminKasir['pelunasan_cash'], 'Admin pelunasan_cash harus 40.000.');
            $this->assertFloatEquals(10000.0, (float) $adminKasir['deposit_cash'], 'Admin deposit_cash harus 10.000.');
            $this->assertFloatEquals(50000.0, (float) $adminKasir['physical_drawer'], 'Admin physical_drawer harus 50.000.');

            $auditLogs = $adminDashService->getSettlementAuditLogs($ctx['business']->id, now('Asia/Jakarta'));
            $this->assertEquals(2, $auditLogs->count(), 'Settlement audit log hanya boleh berisi 2 entri debt_settlement, bukan customer_deposit.');

            return 'Laci +Rp 50.000 (40k kasbon + 10k deposit) konsisten di 4 service, saldo jadi Rp 100.000';
        });

        $this->runScenario('S05', 'Kasbon Transfer Bank + Kelebihan Deposit wajib 1 bukti WA', function (array $ctx, array &$uploadedProofs, array &$tempFiles) {
            $tx1 = $this->createKasbon($ctx, 25000, $ctx['customer']->id);
            $tx2 = $this->createKasbon($ctx, 25000, $ctx['customer']->id);

            $validationFailed = false;
            try {
                $this->submitBulkMarkAsPaid($ctx, [
                    'transaction_ids' => [$tx1->id, $tx2->id],
                    'payment_method'  => 'transfer-bank',
                    'deposit_amount'  => 50000,
                ]);
            } catch (ValidationException $e) {
                $validationFailed = array_key_exists('payment_proof', $e->errors());
            }
            $this->assertTrue($validationFailed, 'Tanpa bukti transfer harus gagal validasi payment_proof.');

            $proof = $this->makeDummyPngUpload($tempFiles, 'bukti-kasbon-tf.png');
            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => [$tx1->id, $tx2->id],
                'payment_method'  => 'transfer-bank',
                'deposit_amount'  => 50000,
            ], [
                'payment_proof' => $proof,
            ]);

            $tx1->refresh();
            $tx2->refresh();
            if (!empty($tx1->payment_proof)) {
                $uploadedProofs[] = $tx1->payment_proof;
            }

            $this->assertTrue($res['session']->has('success'), 'Transfer dengan 1 bukti WA harus sukses.');
            $this->assertTrue(!empty($tx1->payment_proof) && $tx1->payment_proof === $tx2->payment_proof, 'Bukti transfer harus menempel di kedua nota.');

            $ctx['customer']->refresh();
            $this->assertFloatEquals(140000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus jadi 140.000.');
            $this->assertFloatEquals(
                0.0,
                (float) CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->where('affects_drawer', 1)->sum('amount'),
                'Transfer bank tidak boleh mempengaruhi laci.'
            );

            return 'Diblokir tanpa bukti; sukses dengan 1 bukti WA, saldo jadi Rp 140.000';
        });

        $this->runScenario('S06', 'Kasbon Parsial POS (50k - 20k deposit = 30k) di Dashboard & Pelunasan', function (array $ctx) {
            $tx = $this->createKasbon($ctx, 50000, $ctx['customer']->id, 20000, 30000);

            $dash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(30000.0, (float) $dash['totalKasbonAmount'], 'totalKasbonAmount harus menghitung sisa bersih 30.000.');

            $res = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ]);
            $this->assertTrue($res['session']->has('success'), 'Pelunasan sisa 30k via deposit harus sukses.');

            $tx->refresh();
            $ctx['customer']->refresh();
            $this->assertFloatEquals(50000.0, (float) $tx->deposit_used, 'deposit_used harus terakumulasi jadi 50.000.');
            $this->assertFloatEquals(0.0, (float) $tx->net_payable_amount, 'net_payable_amount harus 0.');
            $this->assertFloatEquals(60000.0, (float) $ctx['customer']->deposit_balance, 'Saldo deposit harus berkurang 30k menjadi 60.000.');

            return 'Tagihan bersih Rp 30.000 terpotong pas, sisa deposit Rp 60.000';
        });

        $this->runScenario('S07', 'Guard Pelanggan Umum (customer_id NULL) dilarang pakai/titip deposit', function (array $ctx) {
            $tx = $this->createKasbon($ctx, 20000, null);

            $res1 = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'deposit',
            ]);
            $this->assertTrue($res1['session']->has('error'), 'Pelanggan umum harus ditolak memakai metode deposit.');

            $res2 = $this->submitBulkMarkAsPaid($ctx, [
                'transaction_ids' => [$tx->id],
                'payment_method'  => 'cash',
                'deposit_amount'  => 5000,
            ]);
            $this->assertTrue($res2['session']->has('error'), 'Pelanggan umum harus ditolak menitipkan kelebihan ke deposit.');

            $tx->refresh();
            $this->assertEquals('pending', $tx->payment_status, 'Transaksi harus tetap pending.');

            return 'Pelanggan umum ditolak aman pada kedua mode deposit';
        });

        $this->runScenario('S08', 'Kasus Efan Rp 50.000 Cash (Trip 20k + Kasbon Pagi 15k + Deposit 15k)', function (array $ctx) {
            $ctx['customer']->update(['deposit_balance' => 0]);

            $morningKasbon = $this->createKasbon($ctx, 15000, $ctx['customer']->id, 0, 15000, 'TRIP-PAGI-01', now()->setTime(7, 0));
            $afternoonTrip = $this->createDeliveringTrip($ctx, 20000, $ctx['customer']->id, 'TRIP-EFAN-50K', now()->setTime(13, 0));

            $res = $this->submitCompleteDeliveryTrip($ctx, [
                'trip_code'       => 'TRIP-EFAN-50K',
                'payments'        => [$afternoonTrip->id => 'cash'],
                'order_settled'   => [$afternoonTrip->id => '1'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 15000],
            ]);
            $this->assertTrue($res['session']->has('success'), 'Penyelesaian trip Efan Rp 50k harus berhasil.');

            $morningKasbon->refresh();
            $afternoonTrip->refresh();
            $ctx['customer']->refresh();

            $this->assertEquals('paid', $morningKasbon->payment_status, 'Kasbon pagi harus paid.');
            $this->assertEquals('paid', $afternoonTrip->payment_status, 'Order siang harus paid.');
            $this->assertEquals('delivered', $afternoonTrip->order_status, 'Order siang harus delivered.');
            $this->assertFloatEquals(15000.0, (float) $ctx['customer']->deposit_balance, 'Deposit Efan harus 15.000.');
            $this->assertFloatEquals(35000.0, (float) $ctx['customer']->total_purchases, 'Total purchases Efan harus 35.000.');

            $dash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(35000.0, (float) $dash['pelunasanCashAmount'], 'pelunasanCashAmount harus 35.000.');
            $this->assertFloatEquals(15000.0, (float) $dash['depositCashAmount'], 'depositCashAmount harus 15.000.');
            $this->assertFloatEquals(50000.0, (float) $dash['myNetCashToday'], 'myNetCashToday harus pas Rp 50.000.');
            $this->assertFloatEquals(0.0, (float) $dash['totalKasbonAmount'], 'Piutang kasbon harus 0.');

            return 'Kasbon pagi + siang lunas (35k), deposit +15k, laci pas Rp 50.000';
        });

        $this->runScenario('S09', 'Kasus Efan Rp 25.000 Cash FIFO (Pagi 15k Lunas, Siang 20k Kasbon, +10k Deposit)', function (array $ctx) {
            $ctx['customer']->update(['deposit_balance' => 0]);

            $morningKasbon = $this->createKasbon($ctx, 15000, $ctx['customer']->id, 0, 15000, 'TRIP-PAGI-02', now()->setTime(7, 0));
            $afternoonTrip = $this->createDeliveringTrip($ctx, 20000, $ctx['customer']->id, 'TRIP-EFAN-25K', now()->setTime(13, 0));

            $res = $this->submitCompleteDeliveryTrip($ctx, [
                'trip_code'       => 'TRIP-EFAN-25K',
                'payments'        => [$afternoonTrip->id => 'cash'],
                'order_settled'   => [$afternoonTrip->id => '0'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 10000],
            ]);
            $this->assertTrue($res['session']->has('success'), 'Penyelesaian trip Efan Rp 25k FIFO harus berhasil.');

            $morningKasbon->refresh();
            $afternoonTrip->refresh();
            $ctx['customer']->refresh();

            $this->assertEquals('paid', $morningKasbon->payment_status, 'Kasbon pagi (tertua) harus paid.');
            $this->assertEquals('pending', $afternoonTrip->payment_status, 'Order siang harus tetap pending kasbon.');
            $this->assertEquals('kasbon', $afternoonTrip->payment_method, 'Order siang harus berstatus metode kasbon.');
            $this->assertEquals('delivered', $afternoonTrip->order_status, 'Order siang harus delivered.');
            $this->assertFloatEquals(10000.0, (float) $ctx['customer']->deposit_balance, 'Sisa 10k harus masuk deposit.');

            $dash = app(KasirDashboardService::class)->getDashboardData($ctx['kasir']->id);
            $this->assertFloatEquals(25000.0, (float) $dash['myNetCashToday'], 'Laci kasir harus pas Rp 25.000.');
            $this->assertFloatEquals(20000.0, (float) $dash['totalKasbonAmount'], 'Piutang kasbon tersisa harus Rp 20.000.');

            return 'FIFO: Pagi lunas 15k, siang jadi kasbon 20k, deposit +10k, laci Rp 25.000';
        });

        $this->runScenario('S10', 'Trip Transfer Bank wajib 1 bukti WA & menempel ke order + kasbon lama', function (array $ctx, array &$uploadedProofs, array &$tempFiles) {
            $ctx['customer']->update(['deposit_balance' => 0]);

            $morningKasbon = $this->createKasbon($ctx, 15000, $ctx['customer']->id, 0, 15000, 'TRIP-PAGI-03', now()->setTime(7, 0));
            $afternoonTrip = $this->createDeliveringTrip($ctx, 20000, $ctx['customer']->id, 'TRIP-EFAN-TF', now()->setTime(13, 0));

            $validationFailed = false;
            try {
                $this->submitCompleteDeliveryTrip($ctx, [
                    'trip_code'       => 'TRIP-EFAN-TF',
                    'payments'        => [$afternoonTrip->id => 'transfer-bank'],
                    'order_settled'   => [$afternoonTrip->id => '1'],
                    'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                    'deposits'        => [$afternoonTrip->id => 15000],
                ]);
            } catch (ValidationException $e) {
                $validationFailed = array_key_exists("payment_proofs.{$afternoonTrip->id}", $e->errors());
            }
            $this->assertTrue($validationFailed, 'Transfer Bank di Pengiriman Driver tanpa bukti wajib ditolak.');

            $proof = $this->makeDummyPngUpload($tempFiles, 'bukti-trip-tf.png');
            $res = $this->submitCompleteDeliveryTrip($ctx, [
                'trip_code'       => 'TRIP-EFAN-TF',
                'payments'        => [$afternoonTrip->id => 'transfer-bank'],
                'order_settled'   => [$afternoonTrip->id => '1'],
                'settled_kasbons' => [$afternoonTrip->id => [$morningKasbon->id]],
                'deposits'        => [$afternoonTrip->id => 15000],
            ], [
                'payment_proofs' => [$afternoonTrip->id => $proof],
            ]);

            $morningKasbon->refresh();
            $afternoonTrip->refresh();
            $ctx['customer']->refresh();
            if (!empty($afternoonTrip->payment_proof)) {
                $uploadedProofs[] = $afternoonTrip->payment_proof;
            }

            $this->assertTrue($res['session']->has('success'), 'Trip Transfer Bank dengan 1 bukti WA harus sukses.');
            $this->assertTrue(
                !empty($afternoonTrip->payment_proof) && $afternoonTrip->payment_proof === $morningKasbon->payment_proof,
                'Bukti WA harus menempel pada order trip & kasbon lama.'
            );
            $this->assertFloatEquals(15000.0, (float) $ctx['customer']->deposit_balance, 'Deposit harus bertambah 15.000.');
            $this->assertFloatEquals(
                0.0,
                (float) CashFlow::withoutGlobalScopes()->where('business_id', $ctx['business']->id)->where('affects_drawer', 1)->sum('amount'),
                'Transfer bank tidak boleh masuk laci.'
            );

            return 'Diblokir tanpa bukti; sukses 1 bukti untuk semua, affects_drawer=0';
        });

        $this->runScenario('S11', 'Render Surat Jalan Cetak Driver menampilkan Kasbon Lama & Gabungan', function (array $ctx) {
            $this->createKasbon($ctx, 15000, $ctx['customer']->id, 0, 15000, 'TRIP-PAGI-SJ', now()->setTime(7, 0));
            $this->createDeliveringTrip($ctx, 20000, $ctx['customer']->id, 'TRIP-EFAN-SJ', now()->setTime(13, 0));

            $html = app(PosController::class)->deliveryReceipt('TRIP-EFAN-SJ')->render();

            $this->assertTrue(str_contains($html, '15.000'), 'Surat Jalan harus menampilkan nominal kasbon lalu 15.000.');
            $this->assertTrue(str_contains($html, '35.000'), 'Surat Jalan harus menampilkan total gabungan 35.000.');
            $this->assertTrue(str_contains($html, 'Total Tagih Gabungan'), 'Surat Jalan harus menampilkan label Total Tagih Gabungan.');

            return 'View delivery_receipt ter-render valid dengan info gabungan Rp 35.000';
        });

        $this->runScenario('S12', 'Render Blade Dashboard Kasir dengan Trip + Kasbon Lama + Saldo Deposit', function (array $ctx) {
            $this->createKasbon($ctx, 15000, $ctx['customer']->id, 0, 15000, 'TRIP-PAGI-DB', now()->setTime(7, 0));
            $this->createDeliveringTrip($ctx, 20000, $ctx['customer']->id, 'TRIP-EFAN-DB', now()->setTime(13, 0));

            View::share('errors', new ViewErrorBag());
            View::share('active_branch_id', $ctx['branch']->id);
            View::share('active_branch_name', $ctx['branch']->name);
            View::share('active_branch_code', $ctx['branch']->code);
            View::share('available_branches', collect([$ctx['branch']]));

            $html = app(DashboardController::class)->index()->render();

            $this->assertTrue(str_contains($html, 'Lunas + Kasbon'), 'Dashboard harus memuat tombol cepat Lunas + Kasbon.');
            $this->assertTrue(str_contains($html, 'Kasbon Lalu'), 'Dashboard harus memuat badge Kasbon Lalu.');
            $this->assertTrue(str_contains($html, 'Saldo Deposit'), 'Dashboard harus memuat info Saldo Deposit.');

            return 'View kasir.dashboard.index ter-render penuh tanpa error Blade';
        });

        $this->newLine();
        $this->table(['Kode', 'Skenario Pengujian', 'Status', 'Detail'], $this->rows);
        $this->info(sprintf('Ringkasan: %d PASS | %d FAIL (Total %d skenario, 100%% DB rollback)', $this->passed, $this->failed, $this->passed + $this->failed));

        return $this->failed === 0 ? 0 : 1;
    }

    /**
     * Jalankan 1 skenario dalam transaksi DB yang selalu di-rollback dan bersihkan file sementara.
     */
    protected function runScenario(string $code, string $title, callable $callback): void
    {
        $baseLevel = DB::transactionLevel();
        $uploadedProofs = [];
        $tempFiles = [];
        $businessId = 0;

        $session = new SessionStore('kasbon_verify', new ArraySessionHandler(10));
        $session->start();
        app('redirect')->setSession($session);

        DB::beginTransaction();
        try {
            $ctx = $this->createFixtureContext($session);
            $businessId = (int) $ctx['business']->id;

            $detail = (string) $callback($ctx, $uploadedProofs, $tempFiles);

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
            foreach ($uploadedProofs as $path) {
                ProofUploadService::deleteProof($path);
            }
            foreach ($tempFiles as $tmpFile) {
                if (is_string($tmpFile) && file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
            if ($businessId > 0) {
                Cache::forget("biz_{$businessId}_cache_ver");
            }
            while (DB::transactionLevel() > $baseLevel) {
                DB::rollBack();
            }
            Auth::forgetGuards();
            app(BranchContext::class)->setNone();
        }
    }

    /**
     * Bangun seluruh fixture bisnis/cabang/user/produk/pelanggan terisolasi yang patuh MySQL strict mode.
     *
     * @return array<string, mixed>
     */
    protected function createFixtureContext(SessionStore $session): array
    {
        $admin = User::create([
            'name'      => 'Admin Verify',
            'email'     => 'adm-' . Str::random(8) . '@verify.local',
            'password'  => 'secret123',
            'role'      => 'admin',
            'is_active' => true,
        ]);

        $business = Business::create([
            'name'     => 'Verify Kasbon Biz ' . Str::random(5),
            'owner_id' => $admin->id,
        ]);
        $admin->update(['business_id' => $business->id]);

        $branch = Branch::create([
            'business_id' => $business->id,
            'name'        => 'Cabang Verify',
            'code'        => 'CBV' . random_int(100, 999),
            'is_main'     => true,
            'is_active'   => true,
            'manager_id'  => $admin->id,
        ]);
        $admin->update(['branch_id' => $branch->id]);

        $kasir = User::create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Kasir Verify',
            'email'       => 'ksr-' . Str::random(8) . '@verify.local',
            'password'    => 'secret123',
            'role'        => 'kasir',
            'is_active'   => true,
        ]);
        $kasir->syncBranches([$branch->id], $branch->id);

        $driver = User::create([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => 'Driver Verify',
            'email'       => 'drv-' . Str::random(8) . '@verify.local',
            'password'    => 'secret123',
            'role'        => 'driver',
            'is_active'   => true,
        ]);
        $driver->syncBranches([$branch->id], $branch->id);

        $prodCat = ProductCategory::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'name'        => 'Es Kristal Verify',
            'is_active'   => true,
            'created_by'  => $admin->id,
        ]);

        $prod15 = Product::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'category_id' => $prodCat->id,
            'sku'         => 'V15-' . Str::random(6),
            'name'        => 'Es Kristal 15kg',
            'unit'        => 'Ball',
            'base_price'  => 15000,
            'cost_price'  => 7000,
            'is_active'   => true,
            'is_service'  => false,
            'created_by'  => $admin->id,
        ]);

        $prod20 = Product::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'category_id' => $prodCat->id,
            'sku'         => 'V20-' . Str::random(6),
            'name'        => 'Es Kristal 20kg',
            'unit'        => 'Ball',
            'base_price'  => 20000,
            'cost_price'  => 9000,
            'is_active'   => true,
            'is_service'  => false,
            'created_by'  => $admin->id,
        ]);

        $incomeCat = ExpenseCategory::withoutGlobalScopes()->create([
            'business_id'   => $business->id,
            'name'          => 'Pendapatan Verify',
            'type'          => 'income',
            'category_type' => 'Operasional',
            'is_cogs'       => false,
            'is_active'     => true,
            'created_by'    => $admin->id,
        ]);

        $customer = Customer::withoutGlobalScopes()->create([
            'business_id'     => $business->id,
            'name'            => 'Efan Verify',
            'phone'           => '0899' . random_int(1000000, 9999999),
            'join_date'       => now()->toDateString(),
            'status'          => 'active',
            'deposit_balance' => 90000,
            'total_purchases' => 0,
            'created_by'      => $admin->id,
        ]);

        Auth::guard('web')->setUser($kasir);
        Auth::shouldUse('web');
        app(BranchContext::class)->setBranch($branch->id, $business->id);

        return compact(
            'admin',
            'business',
            'branch',
            'kasir',
            'driver',
            'prod15',
            'prod20',
            'incomeCat',
            'customer',
            'session'
        );
    }

    protected function createKasbon(
        array $ctx,
        float $totalAmount,
        ?int $customerId,
        float $depositUsed = 0,
        ?float $netPayable = null,
        ?string $tripCode = null,
        ?\DateTimeInterface $txDate = null
    ): Transaction {
        $net = $netPayable ?? max(0, $totalAmount - $depositUsed);

        $tx = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $ctx['business']->id,
            'branch_id'              => $ctx['branch']->id,
            'type'                   => 'sale',
            'customer_id'            => $customerId,
            'driver_id'              => $tripCode ? $ctx['driver']->id : null,
            'delivery_trip_code'     => $tripCode,
            'total_amount'           => $totalAmount,
            'deposit_used'           => $depositUsed,
            'net_payable_amount'     => $net,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => $tripCode ? 'delivered' : null,
            'delivery_status'        => $tripCode ? 'delivered' : 'pending',
            'status'                 => 'completed',
            'transaction_date'       => $txDate ?? now()->subDay(),
            'created_by'             => $ctx['kasir']->id,
        ]);

        $tx->details()->create([
            'product_id'  => $ctx['prod15']->id,
            'quantity'    => 1,
            'cost_price'  => 7000,
            'unit_price'  => $totalAmount,
            'total_price' => $totalAmount,
        ]);

        return $tx;
    }

    protected function createDeliveringTrip(
        array $ctx,
        float $totalAmount,
        ?int $customerId,
        string $tripCode,
        ?\DateTimeInterface $txDate = null
    ): Transaction {
        $tx = Transaction::withoutGlobalScopes()->create([
            'business_id'            => $ctx['business']->id,
            'branch_id'              => $ctx['branch']->id,
            'type'                   => 'sale',
            'customer_id'            => $customerId,
            'driver_id'              => $ctx['driver']->id,
            'delivery_trip_code'     => $tripCode,
            'total_amount'           => $totalAmount,
            'deposit_used'           => 0,
            'net_payable_amount'     => $totalAmount,
            'payment_method'         => 'kasbon',
            'initial_payment_method' => 'kasbon',
            'payment_status'         => 'pending',
            'order_status'           => 'delivering',
            'delivery_status'        => 'delivering',
            'status'                 => 'completed',
            'transaction_date'       => $txDate ?? now(),
            'created_by'             => $ctx['kasir']->id,
        ]);

        $tx->details()->create([
            'product_id'  => $ctx['prod20']->id,
            'quantity'    => 1,
            'cost_price'  => 9000,
            'unit_price'  => $totalAmount,
            'total_price' => $totalAmount,
        ]);

        return $tx;
    }

    /**
     * @return array{response: RedirectResponse, session: SessionStore}
     */
    protected function submitBulkMarkAsPaid(array $ctx, array $data, array $files = []): array
    {
        $session = $ctx['session'];
        $session->forget(['success', 'error', 'warning', 'errors']);

        $request = Request::create('/kasir/transactions/bulk-mark-as-paid', 'POST', $data, [], $files);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());

        $formRequest = BulkMarkAsPaidRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        $response = app(TransactionController::class)->bulkMarkAsPaid($formRequest);

        return ['response' => $response, 'session' => $session];
    }

    /**
     * @return array{response: RedirectResponse, session: SessionStore}
     */
    protected function submitCompleteDeliveryTrip(array $ctx, array $data, array $files = []): array
    {
        $session = $ctx['session'];
        $session->forget(['success', 'error', 'warning', 'errors']);

        $request = Request::create('/kasir/transactions/delivery-trip/complete', 'POST', $data, [], $files);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());

        $formRequest = CompleteDeliveryTripRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app('redirect'));
        $formRequest->validateResolved();

        $response = app(TransactionController::class)->completeDeliveryTrip($formRequest);

        return ['response' => $response, 'session' => $session];
    }

    protected function makeDummyPngUpload(array &$tempFiles, string $filename = 'bukti-wa.png'): UploadedFile
    {
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kb_ver_' . Str::random(10) . '.png';
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($tmpPath, $pngBytes);
        $tempFiles[] = $tmpPath;

        return new UploadedFile($tmpPath, $filename, 'image/png', null, true);
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
            throw new \RuntimeException($message . " (expected: " . var_export($expected, true) . ", actual: " . var_export($actual, true) . ")");
        }
    }

    protected function assertFloatEquals(float $expected, float $actual, string $message): void
    {
        if (abs($expected - $actual) > 0.01) {
            throw new \RuntimeException($message . " (expected: {$expected}, actual: {$actual})");
        }
    }
}
