<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\CashFlow;
use App\Models\CashierShiftClosing;
use App\Models\Customer;
use App\Models\CustomerDepositLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class AuditBusinessFinanceRangeCommand extends Command
{
    protected $signature = 'finance:audit-range
                            {--business-id=10 : ID bisnis yang diaudit}
                            {--from= : Tanggal awal YYYY-MM-DD (default: tgl 20 bulan berjalan Asia/Jakarta)}
                            {--to= : Tanggal akhir YYYY-MM-DD (default: tgl 27 bulan berjalan Asia/Jakarta)}
                            {--all-tx : Tampilkan seluruh baris transaksi pada rentang tanggal}
                            {--save : Simpan salinan laporan teks ke storage/logs/}';

    protected $description = 'Audit forensik read-only seluruh transaksi, CashFlow, dan mutasi deposit untuk rentang tanggal tertentu';

    /** @var array<int, string> */
    protected array $reportBuffer = [];

    public function handle(): int
    {
        $businessId = (int) ($this->option('business-id') ?: 10);
        $nowWib = Carbon::now('Asia/Jakarta');

        $fromDate = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), 'Asia/Jakarta')->startOfDay()
            : $nowWib->copy()->day(20)->startOfDay();
        $toDate = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), 'Asia/Jakarta')->endOfDay()
            : $nowWib->copy()->day(27)->endOfDay();

        if ($fromDate->gt($toDate)) {
            $this->error("Tanggal --from ({$fromDate->toDateString()}) tidak boleh lebih besar dari --to ({$toDate->toDateString()}).");
            return 1;
        }

        $fromStr = $fromDate->toDateString();
        $toStr = $toDate->toDateString();
        $showAllTx = (bool) $this->option('all-tx');

        $hasDepositCols = Schema::hasTable('customer_deposit_logs') && Schema::hasColumn('transactions', 'deposit_used');
        $hasReversalCols = $hasDepositCols && Schema::hasColumn('customer_deposit_logs', 'reversed_at');

        $this->emitHeader("AUDIT FORENSIK DATA TRANSAKSI, ARUS KAS & DEPOSIT | BUSINESS #{$businessId} ({$fromStr} s/d {$toStr})");
        $this->emitLine('Waktu Server   : ' . Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB');
        $this->emitLine('Skema Deposit  : ' . ($hasDepositCols ? 'TERPASANG' : 'BELUM MIGRASI') . ' | Audit Reversal: ' . ($hasReversalCols ? 'TERPASANG' : 'BELUM MIGRASI'));

        $branches = Branch::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->pluck('name', 'id');

        // =====================================================================
        // 1. RINGKASAN TRANSAKSI PENJUALAN PER TANGGAL & CABANG
        // =====================================================================
        $this->emitHeader('1. RINGKASAN TRANSAKSI PENJUALAN (transactions) PER TANGGAL & CABANG');

        $transactions = Transaction::withoutGlobalScopes()
            ->with(['customer', 'branch', 'createdBy', 'driver'])
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->whereBetween('transaction_date', [$fromDate->copy()->startOfDay(), $toDate->copy()->endOfDay()])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $txGrouped = $transactions->groupBy(function ($tx) use ($branches) {
            $d = $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : '-';
            $b = $branches->get($tx->branch_id, 'Cabang #' . ($tx->branch_id ?? 0));
            return $d . '|' . $b;
        });

        $txSummaryRows = [];
        foreach ($txGrouped as $key => $items) {
            [$tgl, $cab] = explode('|', $key, 2);
            $activeItems = $items->filter(fn ($t) => $t->status !== 'cancelled' && empty($t->voided_at));
            $paidCount = $activeItems->where('payment_status', 'paid')->count();
            $pendingCount = $activeItems->where('payment_status', 'pending')->count();
            $deliveringCount = $activeItems->filter(fn ($t) => $t->order_status === 'delivering' || $t->delivery_status === 'delivering')->count();
            $voidCount = $items->count() - $activeItems->count();

            $txSummaryRows[] = [
                $tgl,
                $cab,
                $items->count(),
                $paidCount,
                $pendingCount,
                $deliveringCount,
                $voidCount,
                'Rp ' . number_format((float) $activeItems->sum('total_amount'), 0, ',', '.'),
                'Rp ' . number_format((float) $activeItems->sum(fn ($t) => (float) ($t->deposit_used ?? 0)), 0, ',', '.'),
                'Rp ' . number_format((float) $activeItems->sum(fn ($t) => (float) $t->effective_payable_amount), 0, ',', '.'),
            ];
        }

        if (empty($txSummaryRows)) {
            $this->emitLine('Tidak ada transaksi pada rentang tanggal ini.');
        } else {
            $this->emitTable(
                ['Tanggal', 'Cabang', 'Total', 'Lunas', 'Kasbon', 'Mengantar', 'Void', 'Omset Kotor', 'Potong Deposit', 'Tagih Bersih'],
                $txSummaryRows
            );
        }

        // =====================================================================
        // 2. REKONSILIASI ARUS KAS (cash_flow) & TUTUP SHIFT KASIR
        // =====================================================================
        $this->emitHeader('2. RINGKASAN ARUS KAS (cash_flow) PER TANGGAL, FLOW_TYPE & METODE');

        $cashFlows = CashFlow::withoutGlobalScopes()
            ->with(['createdBy', 'branch'])
            ->where('business_id', $businessId)
            ->whereBetween(DB::raw('DATE(date)'), [$fromStr, $toStr])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $cfGrouped = $cashFlows->groupBy(function ($cf) {
            $d = $cf->date ? Carbon::parse($cf->date)->format('Y-m-d') : '-';
            $ft = $cf->flow_type ?: '(NULL_LEGACY)';
            $pm = strtolower((string) ($cf->payment_method ?: '-'));
            $drw = $cf->affects_drawer ? 'LACI(1)' : 'NON-LACI(0)';
            return "{$d}|{$cf->type}|{$ft}|{$pm}|{$drw}";
        });

        $cfRows = [];
        foreach ($cfGrouped as $key => $rows) {
            [$d, $type, $ft, $pm, $drw] = explode('|', $key, 5);
            $cfRows[] = [
                $d,
                strtoupper($type),
                $ft,
                strtoupper($pm),
                $drw,
                $rows->count(),
                'Rp ' . number_format((float) $rows->sum('amount'), 0, ',', '.'),
            ];
        }

        if (empty($cfRows)) {
            $this->emitLine('Tidak ada mutasi cash_flow pada rentang tanggal ini.');
        } else {
            $this->emitTable(['Tanggal', 'Tipe', 'Flow Type', 'Metode', 'Laci', 'Jml', 'Total Nominal'], $cfRows);
        }

        $closings = CashierShiftClosing::withoutGlobalScopes()
            ->with(['branch', 'user'])
            ->where('business_id', $businessId)
            ->whereBetween('date', [$fromStr, $toStr])
            ->orderBy('date', 'asc')
            ->get();

        if ($closings->isNotEmpty()) {
            $this->emitLine('--- Data Tutup Shift Kasir (cashier_shift_closings) ---');
            $closingRows = [];
            foreach ($closings as $cl) {
                $closingRows[] = [
                    Carbon::parse($cl->date)->format('Y-m-d'),
                    $cl->branch->name ?? ('Cabang #' . $cl->branch_id),
                    $cl->user->name ?? ('User #' . $cl->user_id),
                    'Rp ' . number_format((float) $cl->opening_cash, 0, ',', '.'),
                    'Rp ' . number_format((float) $cl->cash_sales, 0, ',', '.'),
                    'Rp ' . number_format((float) $cl->cash_debt_collection, 0, ',', '.'),
                    'Rp ' . number_format((float) $cl->expected_drawer_cash, 0, ',', '.'),
                    'Rp ' . number_format((float) $cl->actual_cash_counted, 0, ',', '.'),
                    'Rp ' . number_format((float) $cl->variance, 0, ',', '.'),
                ];
            }
            $this->emitTable(
                ['Tanggal', 'Cabang', 'Kasir', 'Modal Awal', 'Tunai POS', 'Pelunasan', 'Expected Laci', 'Hitung Fisik', 'Selisih'],
                $closingRows
            );
        }

        // =====================================================================
        // 3. AUDIT SALDO DEPOSIT PELANGGAN (ALL-TIME LEDGER VS CUSTOMERS)
        // =====================================================================
        $this->emitHeader('3. AUDIT KONSISTENSI SALDO DEPOSIT (customers.deposit_balance vs Ledger All-Time)');

        $rangeDepositLogs = collect();
        $depositAuditMismatches = [];

        if ($hasDepositCols) {
            $rangeDepositLogs = CustomerDepositLog::withoutGlobalScopes()
                ->with(['customer', 'branch', 'createdBy', 'reversedBy', 'transaction', 'cashFlow.createdBy'])
                ->where('business_id', $businessId)
                ->whereBetween(DB::raw('DATE(created_at)'), [$fromStr, $toStr])
                ->orderBy('id', 'asc')
                ->get();

            $activeCustomerIds = Customer::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('deposit_balance', '!=', 0)
                ->pluck('id')
                ->merge($rangeDepositLogs->pluck('customer_id'))
                ->unique()
                ->values();

            $ledgerAllTime = CustomerDepositLog::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereIn('customer_id', $activeCustomerIds->isEmpty() ? [0] : $activeCustomerIds)
                ->selectRaw("
                    customer_id,
                    SUM(CASE WHEN type IN ('credit', 'void_restore') THEN amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN type IN ('debit', 'refund') THEN amount ELSE 0 END) as total_out,
                    COUNT(*) as total_logs
                ")
                ->groupBy('customer_id')
                ->get()
                ->keyBy('customer_id');

            $auditCustomers = Customer::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereIn('id', $activeCustomerIds->isEmpty() ? [0] : $activeCustomerIds)
                ->orderBy('name')
                ->get();

            $custBalRows = [];
            foreach ($auditCustomers as $cust) {
                $dbBal = round((float) ($cust->deposit_balance ?? 0), 2);
                $agg = $ledgerAllTime->get($cust->id);
                $totalIn = $agg ? round((float) $agg->total_in, 2) : 0.0;
                $totalOut = $agg ? round((float) $agg->total_out, 2) : 0.0;
                $ledgerBal = round($totalIn - $totalOut, 2);
                $diff = round($dbBal - $ledgerBal, 2);
                $status = abs($diff) > 0.01 ? 'MISMATCH!' : 'KLOP';

                if (abs($diff) > 0.01) {
                    $depositAuditMismatches[] = [
                        'customer_id' => $cust->id,
                        'name'        => $cust->name,
                        'db_balance'  => $dbBal,
                        'ledger_bal'  => $ledgerBal,
                        'diff'        => $diff,
                    ];
                }

                $custBalRows[] = [
                    '#' . $cust->id,
                    $cust->name,
                    $cust->phone ?? '-',
                    'Rp ' . number_format($totalIn, 0, ',', '.'),
                    'Rp ' . number_format($totalOut, 0, ',', '.'),
                    'Rp ' . number_format($ledgerBal, 0, ',', '.'),
                    'Rp ' . number_format($dbBal, 0, ',', '.'),
                    'Rp ' . number_format($diff, 0, ',', '.'),
                    $status,
                ];
            }

            if (empty($custBalRows)) {
                $this->emitLine('Tidak ada pelanggan dengan saldo deposit aktif atau mutasi deposit pada rentang ini.');
            } else {
                $this->emitTable(
                    ['ID', 'Pelanggan', 'WA', 'Masuk (All)', 'Keluar (All)', 'Hitungan Ledger', 'Saldo Web', 'Selisih', 'Status'],
                    $custBalRows
                );
            }

            // =================================================================
            // 4. DETAIL MUTASI DEPOSIT RENTANG TANGGAL
            // =================================================================
            $this->emitHeader("4. DETAIL MUTASI DEPOSIT (customer_deposit_logs) {$fromStr} s/d {$toStr}");

            if ($rangeDepositLogs->isEmpty()) {
                $this->emitLine('Tidak ada mutasi deposit pada rentang tanggal ini.');
            } else {
                $logRows = [];
                foreach ($rangeDepositLogs as $dl) {
                    $isRev = $hasReversalCols && $dl->isReversed();
                    $statusStr = $isRev ? 'VOID (#' . $dl->reversed_by_log_id . ')' : 'AKTIF';
                    $cfInfo = $dl->cash_flow_id
                        ? ('CF#' . $dl->cash_flow_id . ($dl->cashFlow?->affects_drawer ? '[Laci]' : '[NonLaci]'))
                        : '-';

                    $logRows[] = [
                        '#' . $dl->id,
                        $dl->created_at?->format('d/m H:i') ?? '-',
                        $dl->branch->name ?? '-',
                        $dl->customer->name ?? ('Cust#' . $dl->customer_id),
                        strtoupper($dl->type),
                        strtoupper($dl->payment_method ?? '-'),
                        'Rp ' . number_format((float) $dl->amount, 0, ',', '.'),
                        number_format((float) $dl->balance_before, 0, ',', '.') . ' -> ' . number_format((float) $dl->balance_after, 0, ',', '.'),
                        $dl->createdBy->name ?? '-',
                        $cfInfo,
                        $statusStr,
                        mb_strimwidth((string) ($dl->notes ?: '-'), 0, 42, '...'),
                    ];
                }
                $this->emitTable(
                    ['ID', 'Waktu', 'Cabang', 'Pelanggan', 'Tipe', 'Metode', 'Nominal', 'Saldo Bfr->Aft', 'Input By', 'CashFlow', 'Status', 'Catatan'],
                    $logRows
                );
            }
        }

        // =====================================================================
        // 5. DETAIL TRANSAKSI TERKAIT DEPOSIT (ATAU SEMUA DENGAN --all-tx)
        // =====================================================================
        $this->emitHeader($showAllTx
            ? "5. SEMUA TRANSAKSI PENJUALAN ({$fromStr} s/d {$toStr})"
            : "5. TRANSAKSI YANG MENGGUNAKAN / TERKAIT DEPOSIT ({$fromStr} s/d {$toStr})"
        );

        $cfTxMap = $cashFlows
            ->where('type', 'income')
            ->filter(fn ($cf) => !empty($cf->reference_id) && (empty($cf->reference_type) || $cf->reference_type === 'transaction'))
            ->groupBy(fn ($cf) => (string) $cf->reference_id);

        $depTxIds = $rangeDepositLogs->pluck('transaction_id')->filter()->unique()->all();
        $displayTxs = $showAllTx
            ? $transactions
            : $transactions->filter(function ($tx) use ($depTxIds) {
                return (float) ($tx->deposit_used ?? 0) > 0
                    || strtolower((string) $tx->payment_method) === 'deposit'
                    || strtolower((string) $tx->settlement_payment_method) === 'deposit'
                    || in_array($tx->id, $depTxIds, true);
            });

        if ($displayTxs->isEmpty()) {
            $this->emitLine($showAllTx ? 'Tidak ada transaksi.' : 'Tidak ada transaksi terkait deposit pada rentang ini. Tambahkan --all-tx untuk menampilkan semua transaksi.');
        } else {
            $txRows = [];
            foreach ($displayTxs as $tx) {
                $txCfList = $cfTxMap->get((string) $tx->id, collect());
                $cfSummary = $txCfList->isEmpty()
                    ? '0 row'
                    : $txCfList->count() . 'x (Rp ' . number_format((float) $txCfList->sum('amount'), 0, ',', '.') . ')';
                $isVoid = $tx->status === 'cancelled' || !empty($tx->voided_at);

                $txRows[] = [
                    '#' . $tx->id . ($isVoid ? '[VOID]' : ''),
                    $tx->transaction_date?->format('d/m H:i') ?? '-',
                    $tx->branch->name ?? '-',
                    $tx->customer->name ?? 'Umum',
                    'Rp ' . number_format((float) $tx->total_amount, 0, ',', '.'),
                    'Rp ' . number_format((float) ($tx->deposit_used ?? 0), 0, ',', '.'),
                    'Rp ' . number_format((float) $tx->effective_payable_amount, 0, ',', '.'),
                    ($tx->initial_payment_method ?: '-') . ' -> ' . ($tx->payment_method ?: '-'),
                    strtoupper((string) $tx->payment_status),
                    $tx->order_status ?: ($tx->delivery_status ?: '-'),
                    $cfSummary,
                ];
            }
            $this->emitTable(
                ['ID', 'Waktu', 'Cabang', 'Pelanggan', 'Total', 'Pakai Dep', 'Tagih Bersih', 'Metode', 'Bayar', 'Order', 'CashFlow Income'],
                $txRows
            );
        }

        // =====================================================================
        // 6. DETEKSI OTOMATIS ANOMALI / BUG FINANCE
        // =====================================================================
        $this->emitHeader('6. DETEKSI OTOMATIS ANOMALI / POTENSI BUG FINANCE');
        $anomalies = [];

        // A1: Mismatch saldo deposit
        foreach ($depositAuditMismatches as $mm) {
            $anomalies[] = [
                'A1',
                "Cust #{$mm['customer_id']} ({$mm['name']})",
                'CRITICAL',
                "Saldo DB (Rp " . number_format($mm['db_balance'], 0, ',', '.') . ") != Ledger All-Time (Rp " . number_format($mm['ledger_bal'], 0, ',', '.') . "), selisih Rp " . number_format($mm['diff'], 0, ',', '.'),
            ];
        }

        if ($hasDepositCols) {
            // A2: Log deposit credit (non opening_balance) tanpa CashFlow valid
            foreach ($rangeDepositLogs->where('type', 'credit') as $dl) {
                if ($dl->payment_method !== 'opening_balance') {
                    if (empty($dl->cash_flow_id) || !$dl->cashFlow) {
                        $anomalies[] = [
                            'A2',
                            "DepositLog #{$dl->id}",
                            'HIGH',
                            "Setoran {$dl->payment_method} Rp " . number_format((float) $dl->amount, 0, ',', '.') . ' tidak terhubung ke baris cash_flow.',
                        ];
                    }
                }
            }

            // A3: CashFlow deposit / deposit_refund yatim tanpa CustomerDepositLog
            $rangeDepositCfIds = CustomerDepositLog::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereNotNull('cash_flow_id')
                ->pluck('cash_flow_id')
                ->all();

            foreach ($cashFlows->whereIn('flow_type', ['customer_deposit', 'deposit_refund']) as $cf) {
                if (!in_array($cf->id, $rangeDepositCfIds, true)) {
                    $anomalies[] = [
                        'A3',
                        "CashFlow #{$cf->id}",
                        'HIGH',
                        "CashFlow {$cf->flow_type} Rp " . number_format((float) $cf->amount, 0, ',', '.') . ' tidak memiliki pasangan di customer_deposit_logs.',
                    ];
                }
            }

            // A4: Transaction dengan deposit_used > 0 tanpa log debit atau salah net_payable_amount
            $debitTxIds = CustomerDepositLog::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('type', 'debit')
                ->whereNotNull('transaction_id')
                ->pluck('transaction_id')
                ->all();

            foreach ($transactions as $tx) {
                $depUsed = (float) ($tx->deposit_used ?? 0);
                if ($depUsed > 0) {
                    if (!in_array($tx->id, $debitTxIds, true)) {
                        $anomalies[] = [
                            'A4',
                            "Transaction #{$tx->id}",
                            'HIGH',
                            "Memiliki deposit_used Rp " . number_format($depUsed, 0, ',', '.') . ' tetapi tidak tercatat log debit di customer_deposit_logs.',
                        ];
                    }
                    $expectedNet = round(max(0, (float) $tx->total_amount - $depUsed), 2);
                    if (abs($expectedNet - (float) $tx->effective_payable_amount) > 0.01) {
                        $anomalies[] = [
                            'A4',
                            "Transaction #{$tx->id}",
                            'HIGH',
                            "net_payable_amount tidak konsisten dengan total_amount - deposit_used.",
                        ];
                    }
                }
            }

            // A6: Transaction VOID dengan deposit_used > 0 tanpa void_restore
            $voidRestoreTxIds = CustomerDepositLog::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('type', 'void_restore')
                ->whereNotNull('transaction_id')
                ->pluck('transaction_id')
                ->all();

            foreach ($transactions as $tx) {
                $isVoid = $tx->status === 'cancelled' || !empty($tx->voided_at);
                $depUsed = (float) ($tx->deposit_used ?? 0);
                if ($isVoid && $depUsed > 0 && !in_array($tx->id, $voidRestoreTxIds, true)) {
                    $anomalies[] = [
                        'A6',
                        "Transaction #{$tx->id}",
                        'CRITICAL',
                        "Transaksi VOID memakai deposit Rp " . number_format($depUsed, 0, ',', '.') . ' tetapi belum ada log void_restore.',
                    ];
                }
            }
        }

        // A5: Transaction paid non-deposit dengan effective_payable > 0 namun 0 atau >1 CashFlow income
        $allIncomeTxCashFlows = CashFlow::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('type', 'income')
            ->where(function ($q) {
                $q->whereIn('flow_type', ['sale_direct', 'debt_settlement'])
                  ->orWhereNull('flow_type');
            })
            ->whereIn('reference_id', $transactions->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->where(function ($q) {
                $q->where('reference_type', 'transaction')
                  ->orWhereNull('reference_type');
            })
            ->get()
            ->groupBy(fn ($cf) => (string) $cf->reference_id);

        foreach ($transactions as $tx) {
            $isVoid = $tx->status === 'cancelled' || !empty($tx->voided_at);
            if ($isVoid) {
                continue;
            }
            if ($tx->payment_status === 'paid' && $tx->effective_payable_amount > 0 && strtolower((string) $tx->payment_method) !== 'deposit') {
                $matches = $allIncomeTxCashFlows->get((string) $tx->id, collect());
                if ($matches->count() === 0) {
                    $anomalies[] = [
                        'A5',
                        "Transaction #{$tx->id}",
                        'WARN',
                        "Transaksi LUNAS ({$tx->payment_method}, Rp " . number_format($tx->effective_payable_amount, 0, ',', '.') . ') belum memiliki baris CashFlow income.',
                    ];
                } elseif ($matches->count() > 1) {
                    $anomalies[] = [
                        'A5',
                        "Transaction #{$tx->id}",
                        'CRITICAL',
                        "Transaksi memiliki {$matches->count()} baris CashFlow income (indikasi double-counting).",
                    ];
                }
            }
        }

        // A7: CashFlow legacy flow_type NULL pada rentang tanggal
        $nullFlowCount = $cashFlows->whereNull('flow_type')->count();
        if ($nullFlowCount > 0) {
            $anomalies[] = [
                'A7',
                "CashFlow ({$nullFlowCount} baris)",
                'INFO',
                "Terdapat {$nullFlowCount} baris cash_flow dengan flow_type NULL di rentang tanggal ini.",
            ];
        }

        if (empty($anomalies)) {
            $this->info('TIDAK DITEMUKAN ANOMALI DATA PADA RENTANG TANGGAL INI. Seluruh saldo, CashFlow & transaksi konsisten.');
            $this->reportBuffer[] = 'TIDAK DITEMUKAN ANOMALI DATA PADA RENTANG TANGGAL INI.';
        } else {
            $this->emitTable(['Kode', 'Objek', 'Tingkat', 'Penjelasan Anomali'], $anomalies);
        }

        if ($this->option('save')) {
            $filePath = storage_path("logs/audit_business_{$businessId}_{$fromStr}_{$toStr}.txt");
            file_put_contents($filePath, implode(PHP_EOL, $this->reportBuffer) . PHP_EOL);
            $this->newLine();
            $this->info("Laporan lengkap tersimpan di: {$filePath}");
        }

        return 0;
    }

    protected function emitHeader(string $title): void
    {
        $this->newLine();
        $this->info("=== {$title} ===");
        $this->reportBuffer[] = '';
        $this->reportBuffer[] = "=== {$title} ===";
    }

    protected function emitLine(string $line): void
    {
        $this->line($line);
        $this->reportBuffer[] = $line;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    protected function emitTable(array $headers, array $rows): void
    {
        $bufferOut = new BufferedOutput();
        $table = new Table($bufferOut);
        $table->setHeaders($headers)->setRows($rows)->render();
        $rendered = rtrim($bufferOut->fetch());
        $this->output->writeln($rendered);
        $this->reportBuffer[] = $rendered;
    }
}
