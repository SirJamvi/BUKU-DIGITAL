<?php

namespace App\Services\Admin;

use App\Models\CashFlow;
use App\Models\Transaction;
use App\Models\ExpenseCategory;
use App\Models\CapitalTracking;
use App\Models\OwnerProfit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentMethod;
use App\Services\Accounting\CogsCalculator;
use Carbon\Carbon;

class FinancialService
{
    /**
     * Mengambil daftar metode pembayaran aktif
     */
    public function getPaymentMethods()
    {
        return PaymentMethod::where('business_id', Auth::user()->business_id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Menghitung Saldo per Metode Pembayaran (Dinamis Sesuai Filter)
     * [FIXED]: Menggunakan CashFlow income untuk kas masuk riil.
     */
    private function calculatePaymentBalances(array $filters = []): array
    {
        $businessId = Auth::user()->business_id;

        $methods = PaymentMethod::where('business_id', $businessId)->active()->get();

        $balanceData = [];

        foreach ($methods as $method) {
            // PERBAIKAN: Ambil uang masuk riil langsung dari tabel cash_flow
            $incomeQuery = CashFlow::where('business_id', $businessId)
                ->where('type', 'income')
                ->where('payment_method', $method->slug);

            $this->applyDateFilters($incomeQuery, $filters, 'date');
            $income = $incomeQuery->sum('amount');

            // Pengeluaran tetap mengambil dari tabel cash_flow
            $expenseQuery = CashFlow::where('business_id', $businessId)
                ->where('type', 'expense')
                ->where('payment_method', $method->slug);

            $this->applyDateFilters($expenseQuery, $filters, 'date');
            $expense = $expenseQuery->sum('amount');

            $balanceData[] = [
                'name'    => $method->name,
                'slug'    => $method->slug,
                'balance' => $income - $expense,
            ];
        }

        return $balanceData;
    }

    /**
     * Laporan keuangan — VERSI DENGAN PAGINATION.
     * [FIXED]: HPP dihitung dari transaksi, Laba Kotor = Revenue - COGS.
     */
    public function getFinancialReport(array $filters, int $perPage = 15): array
    {
        $businessId = Auth::user()->business_id;

        // Transaksi penjualan (dipakai untuk hitung income & gross profit)
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->with(['details.product', 'customer', 'createdBy']);

        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $totalIncome = $cogsResult['total_income'];
        $totalCogs = $cogsResult['total_cogs'];
        $totalGrossProfit = $cogsResult['total_gross_profit'];

        // Beban Operasional — SUM langsung di database (Murni Expense, is_cogs = 0)
        $expenseQuery = $this->basePureExpenseQuery($businessId);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');

        $netProfit = $totalGrossProfit - $totalExpense;

        $balances = $this->calculatePaymentBalances($filters);

        // Data untuk TABEL rincian arus kas — dipaginate
        $cashFlowListQuery = CashFlow::where('business_id', $businessId)->with('category');
        $this->applyDateFilters($cashFlowListQuery, $filters, 'date');
        $cashFlows = $cashFlowListQuery->latest('date')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'transactions'         => $transactions,
            'cash_flows'           => $cashFlows,
            'total_income'         => $totalIncome,
            'total_cogs'           => $totalCogs,
            'total_expense'        => $totalExpense,
            'total_gross_profit'   => $totalGrossProfit,
            'net_profit'           => $netProfit,
            'balances'             => $balances,
            'filters'              => $filters,
            'missing_cogs_count'   => $cogsResult['missing_cogs_count'],
            'missing_cogs_revenue' => $cogsResult['missing_cogs_revenue'],
        ];
    }

    /**
     * Versi khusus untuk export PDF — ambil SEMUA data arus kas tanpa pagination.
     * [FIXED]: Sinkronisasi rumus Laba Kotor & HPP dengan fungsi laporan utama via CogsCalculator.
     */
    public function getFinancialReportForExport(array $filters): array
    {
        $businessId = Auth::user()->business_id;

        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->with(['details.product', 'customer', 'createdBy']);

        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $totalIncome = $cogsResult['total_income'];
        $totalCogs = $cogsResult['total_cogs'];
        $totalGrossProfit = $cogsResult['total_gross_profit'];

        $cashFlowQuery = CashFlow::where('business_id', $businessId)->with('category');
        $this->applyDateFilters($cashFlowQuery, $filters, 'date');
        $cashFlows = $cashFlowQuery->latest('date')->get();

        // Total beban operasional murni (is_cogs = 0)
        $useCashflowIsCogs = (bool) config('accounting.use_cashflow_is_cogs', false);
        $totalExpense = $cashFlows->filter(function ($flow) use ($useCashflowIsCogs) {
            if ($flow->type !== 'expense') {
                return false;
            }
            if ($useCashflowIsCogs) {
                return (int) ($flow->is_cogs ?? 0) === 0;
            }
            return $flow->category && (int) $flow->category->is_cogs === 0;
        })->sum('amount');

        $netProfit = $totalGrossProfit - $totalExpense;

        $balances = $this->calculatePaymentBalances($filters);

        return [
            'transactions'         => $transactions,
            'cash_flows'           => $cashFlows,
            'total_income'         => $totalIncome,
            'total_cogs'           => $totalCogs,
            'total_expense'        => $totalExpense,
            'total_gross_profit'   => $totalGrossProfit,
            'net_profit'           => $netProfit,
            'balances'             => $balances,
            'filters'              => $filters,
            'missing_cogs_count'   => $cogsResult['missing_cogs_count'],
            'missing_cogs_revenue' => $cogsResult['missing_cogs_revenue'],
        ];
    }

    /**
     * Ringkasan finansial dashboard
     */
    public function getFinancialSummary(): array
    {
        $businessId = Auth::user()->business_id;

        $totalIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->sum('total_amount');

        $totalExpense = $this->basePureExpenseQuery($businessId)->sum('amount');

        // Ambil transaksi penjualan untuk kalkulasi terstandarisasi CogsCalculator
        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->with('details.product')
            ->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $grossProfit = $cogsResult['total_gross_profit'];
        $totalIncome = $cogsResult['total_income'];

        $netProfit = $grossProfit - $totalExpense;

        // Hitung Saldo Realtime (CashFlow Income - CashFlow Expense)
        $totalCashIn = CashFlow::where('business_id', $businessId)->where('type', 'income')->sum('amount');
        $totalCashOut = CashFlow::where('business_id', $businessId)->where('type', 'expense')->sum('amount');
        $netCashFlow = $totalCashIn - $totalCashOut;

        return [
            'total_income'         => $totalIncome,
            'total_expense'        => $totalExpense,
            'gross_profit'         => $grossProfit,
            'net_profit'           => $netProfit,
            'net_cash_flow'        => $netCashFlow,
            'missing_cogs_count'   => $cogsResult['missing_cogs_count'],
            'missing_cogs_revenue' => $cogsResult['missing_cogs_revenue'],
        ];
    }

    /**
     * Analisis ROI
     */
    public function getRoiAnalysis(): array
    {
        $businessId = Auth::user()->business_id;
        $capital = CapitalTracking::where('business_id', $businessId)->first();

        if (!$capital) {
            return $this->getAlternativeRoiAnalysis();
        }

        $totalProfit = OwnerProfit::where('business_id', $businessId)->sum('net_profit');

        $initialCapital = $capital->initial_capital + ($capital->additional_capital ?? 0);
        $roi = ($initialCapital > 0) ? ($totalProfit / $initialCapital) * 100 : 0;

        return [
            'roi'              => round($roi, 2),
            'initial_capital'  => $initialCapital,
            'total_profit'     => $totalProfit,
            'has_capital_data' => true,
            'data_source'      => 'capital_tracking',
        ];
    }

    private function getAlternativeRoiAnalysis(): array
    {
        $summary = $this->getFinancialSummary();
        $estimatedCapital = 10000000;

        return [
            'roi'              => ($estimatedCapital > 0) ? round(($summary['net_profit'] / $estimatedCapital) * 100, 2) : 0,
            'initial_capital'  => $estimatedCapital,
            'total_profit'     => $summary['net_profit'],
            'has_capital_data' => false,
            'data_source'      => 'cash_flow_estimate',
            'warning_message'  => 'Data modal awal belum diinput. Perhitungan ROI menggunakan estimasi.',
        ];
    }

    public function storeOrUpdateCapital(array $data): void
    {
        CapitalTracking::updateOrCreate(
            ['business_id' => Auth::user()->business_id],
            [
                'initial_capital'    => $data['initial_capital'],
                'additional_capital' => $data['additional_capital'] ?? 0,
                'recorded_at'        => now(),
                'updated_by'         => Auth::id(),
            ]
        );
    }

    /**
     * [FIXED]: Standardisasi CogsCalculator dan Revenue - COGS calculation.
     */
    public function processMonthlyClosing(string $period): OwnerProfit
    {
        $businessId = Auth::user()->business_id;
        $date = Carbon::createFromFormat('Y-m', $period);
        $month = $date->month;
        $year = $date->year;

        $existingProfit = OwnerProfit::where('business_id', $businessId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        if ($existingProfit && $existingProfit->status === 'completed') {
            return $existingProfit;
        }

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with(['details.product'])
            ->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $monthlyIncome = $cogsResult['total_income'];
        $monthlyGrossProfit = $cogsResult['total_gross_profit'];

        $monthlyOpexQuery = $this->basePureExpenseQuery($businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth]);
        $monthlyOpex = $monthlyOpexQuery->sum('amount');

        $netProfit = $monthlyGrossProfit - $monthlyOpex;

        return OwnerProfit::updateOrCreate(
            [
                'business_id'  => $businessId,
                'period_month' => $month,
                'period_year'  => $year,
            ],
            [
                'monthly_income'  => $monthlyIncome,
                'monthly_expense' => $monthlyOpex,
                'gross_profit'    => $monthlyGrossProfit,
                'net_profit'      => $netProfit,
                'status'          => 'pending',
                'allocated_at'    => null,
                'allocated_funds' => 0,
                'recorded_at'     => now(),
                'updated_at'      => now(),
            ]
        );
    }

    public function getAvailablePeriodsForClosing(): array
    {
        $businessId = Auth::user()->business_id;

        $transactionPeriods = DB::table('transactions')
            ->select(DB::raw('YEAR(transaction_date) as year, MONTH(transaction_date) as month'))
            ->where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->distinct()
            ->get();

        $expensePeriods = DB::table('cash_flows')
            ->select(DB::raw('YEAR(date) as year, MONTH(date) as month'))
            ->where('business_id', $businessId)
            ->where('type', 'expense')
            ->distinct()
            ->get();

        $periods = collect($transactionPeriods)->merge($expensePeriods)
            ->unique(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            })
            ->sortByDesc(function ($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            });

        $result = [];
        foreach ($periods as $period) {
            $yearMonth = $period->year . '-' . str_pad($period->month, 2, '0', STR_PAD_LEFT);
            $monthName = Carbon::create($period->year, $period->month, 1)->format('F Y');

            $isClosed = OwnerProfit::where('business_id', $businessId)
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->exists();

            $result[] = [
                'period'    => $yearMonth,
                'label'     => $monthName,
                'is_closed' => $isClosed,
                'year'      => $period->year,
                'month'     => $period->month,
            ];
        }

        return $result;
    }

    /**
     * [FIXED]: lazy() and Revenue - COGS calculation.
     */
    public function getClosingSummaryForPeriod(string $period): array
    {
        $businessId = Auth::user()->business_id;
        $date = Carbon::createFromFormat('Y-m', $period);
        $month = $date->month;
        $year = $date->year;

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $monthName = $date->format('F Y');

        $monthlyIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with(['details.product'])
            ->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $monthlyIncome = $cogsResult['total_income'];
        $monthlyGrossProfit = $cogsResult['total_gross_profit'];

        $monthlyOpexQuery = $this->basePureExpenseQuery($businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth]);
        $monthlyOpex = $monthlyOpexQuery->sum('amount');

        $netProfit = $monthlyGrossProfit - $monthlyOpex;

        $existingClosing = OwnerProfit::where('business_id', $businessId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        // Karena query di atas sekarang lazy, count() akan menghabiskan query lagi. Kita pakai direct DB count.
        $transactionsCount = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->count();

        return [
            'period'             => $period,
            'month_name'         => $monthName,
            'monthly_income'     => (float) $monthlyIncome,
            'monthly_expense'    => (float) $monthlyOpex,
            'gross_profit'       => (float) $monthlyGrossProfit,
            'net_profit'         => (float) $netProfit,
            'is_already_closed'  => $existingClosing ? true : false,
            'closing_data'       => $existingClosing,
            'transactions_count'   => $transactionsCount,
            'expenses_count'       => CashFlow::where('business_id', $businessId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->count(),
            'missing_cogs_count'   => $cogsResult['missing_cogs_count'],
            'missing_cogs_revenue' => $cogsResult['missing_cogs_revenue'],
        ];
    }

    public function getUnallocatedProfits(): array
    {
        $businessId = Auth::user()->business_id;

        $unallocatedProfits = OwnerProfit::where('business_id', $businessId)
            ->where('status', 'pending')
            ->where('net_profit', '>', 0)
            ->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get();

        $totalUnallocated = $unallocatedProfits->sum('net_profit');

        return [
            'profits'            => $unallocatedProfits,
            'total_unallocated'  => $totalUnallocated,
        ];
    }

    public function allocateProfits(array $allocations): void
    {
        $businessId = Auth::user()->business_id;

        $unallocatedProfits = OwnerProfit::where('business_id', $businessId)
            ->where('status', 'pending')
            ->where('net_profit', '>', 0)
            ->get();

        $totalAllocated = 0;

        foreach ($unallocatedProfits as $profit) {
            $profit->update([
                'status'          => 'allocated',
                'allocated_at'    => now(),
                'allocated_funds' => $profit->net_profit,
            ]);

            $totalAllocated += $profit->net_profit;
        }

        if ($totalAllocated > 0) {
            foreach ($allocations as $allocation) {
                if ($allocation['amount'] > 0) {
                    CashFlow::create([
                        'business_id' => $businessId,
                        'type'        => 'allocation',
                        'category_id' => null,
                        'amount'      => $allocation['amount'],
                        'description' => 'Alokasi Dana: ' . $allocation['description'],
                        'date'        => now(),
                        'created_by'  => Auth::id(),
                    ]);
                }
            }
        }
    }

    public function initializeBusinessFinancialData(float $initialCapital): array
    {
        $this->storeOrUpdateCapital(['initial_capital' => $initialCapital]);
        $this->processMonthlyClosing(now()->format('Y-m'));

        return [
            'message'         => 'Data finansial awal berhasil diinisialisasi.',
            'initial_capital' => $initialCapital,
        ];
    }

    public function getCashFlowWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy')
            ->latest('date')
            ->paginate($perPage);
    }

    public function getExpensesWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::where('type', 'expense')
            ->where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy')
            ->latest('date')
            ->paginate($perPage);
    }

    public function getExpenseCategories()
    {
        return ExpenseCategory::where('business_id', Auth::user()->business_id)->get();
    }

    public function createExpense(array $data): void
    {
        $rawCategoryName = trim($data['category_name']);
        $businessId = Auth::user()->business_id;

        // Cek case-insensitive agar tidak terjadi duplikasi seperti 'Bensin' vs 'bensin'
        $category = ExpenseCategory::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($rawCategoryName)])
            ->first();

        if (!$category) {
            $category = ExpenseCategory::create([
                'business_id' => $businessId,
                'name'        => $rawCategoryName,
                'type'        => $data['type'] ?? 'Operasional',
                'is_active'   => true,
                'created_by'  => Auth::id(),
                'is_cogs'     => $data['is_cogs'] ?? false,
            ]);
        }

        $fundingSource = $data['funding_source'] ?? 'drawer';
        $affectsDrawer = ($fundingSource === 'drawer');

        $expensePayload = [
            'business_id'    => $businessId,
            'type'           => 'expense',
            'category_id'    => $category->id,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'],
            'funding_source' => $fundingSource,
            'affects_drawer' => $affectsDrawer,
            'description'    => $data['description'],
            'date'           => $data['date'],
            'created_by'     => Auth::id(),
        ];

        if (!empty($data['receipt_image'])) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('cash_flow', 'receipt_image')) {
                $expensePayload['receipt_image'] = $data['receipt_image'];
            }
        }

        CashFlow::create($expensePayload);
    }

    public function updateExpense(CashFlow $expense, array $data): void
    {
        $rawCategoryName = trim($data['category_name']);
        $businessId = Auth::user()->business_id;

        // Cek case-insensitive agar tidak terjadi duplikasi
        $category = ExpenseCategory::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($rawCategoryName)])
            ->first();

        if (!$category) {
            $category = ExpenseCategory::create([
                'business_id' => $businessId,
                'name'        => $rawCategoryName,
                'type'        => 'Operasional',
                'is_active'   => true,
                'created_by'  => Auth::id(),
                'is_cogs'     => false,
            ]);
        }

        $updateData = [
            'category_id'    => $category->id,
            'amount'         => $data['amount'],
            'payment_method' => $data['payment_method'],
            'description'    => $data['description'],
            'date'           => $data['date'],
        ];

        if (isset($data['funding_source'])) {
            $updateData['funding_source'] = $data['funding_source'];
            $updateData['affects_drawer'] = ($data['funding_source'] === 'drawer');
        }

        if (!empty($data['receipt_image'])) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('cash_flow', 'receipt_image')) {
                if ($expense->receipt_image && $expense->receipt_image !== $data['receipt_image']) {
                    \App\Services\ProofUploadService::deleteProof($expense->receipt_image);
                }
                $updateData['receipt_image'] = $data['receipt_image'];
            }
        }

        $expense->update($updateData);
    }

    public function deleteExpense(CashFlow $expense): void
    {
        if ($expense->receipt_image) {
            \App\Services\ProofUploadService::deleteProof($expense->receipt_image);
        }
        $expense->delete();
    }

    public function createExpenseCategory(array $data): void
    {
        $rawName = trim($data['name']);
        $businessId = Auth::user()->business_id;

        $existing = ExpenseCategory::where('business_id', $businessId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($rawName)])
            ->first();

        if (!$existing) {
            ExpenseCategory::create([
                'business_id' => $businessId,
                'name'        => $rawName,
                'type'        => $data['type'] ?? 'Operasional',
                'is_active'   => true,
                'created_by'  => Auth::id(),
                'is_cogs'     => $data['is_cogs'] ?? false,
            ]);
        }
    }

    public function getAllocationHistory(): array
    {
        $businessId = Auth::user()->business_id;

        $allocatedProfits = OwnerProfit::where('business_id', $businessId)
            ->where('status', 'allocated')
            ->orderBy('allocated_at', 'desc')
            ->get();

        $totalAllocated = $allocatedProfits->sum('allocated_funds');

        return [
            'allocations'      => $allocatedProfits,
            'total_allocated'  => $totalAllocated,
        ];
    }

    public function getMonthlyFinancialStats(?int $year = null): array
    {
        $businessId = Auth::user()->business_id;
        $year = $year ?? now()->year;

        $monthlyStats = OwnerProfit::where('business_id', $businessId)
            ->where('period_year', $year)
            ->orderBy('period_month')
            ->get()
            ->keyBy('period_month');

        $stats = [];
        for ($month = 1; $month <= 12; $month++) {
            $stats[$month] = [
                'month'        => $month,
                'month_name'   => Carbon::create()->month($month)->format('F'),
                'income'       => $monthlyStats->get($month)?->monthly_income ?? 0,
                'expense'      => $monthlyStats->get($month)?->monthly_expense ?? 0,
                'gross_profit' => $monthlyStats->get($month)?->gross_profit ?? 0,
                'net_profit'   => $monthlyStats->get($month)?->net_profit ?? 0,
                'status'       => $monthlyStats->get($month)?->status ?? 'not_processed',
            ];
        }

        return [
            'year'               => $year,
            'monthly_stats'      => $stats,
            'total_income'       => collect($stats)->sum('income'),
            'total_expense'      => collect($stats)->sum('expense'),
            'total_gross_profit' => collect($stats)->sum('gross_profit'),
            'total_net_profit'   => collect($stats)->sum('net_profit'),
        ];
    }

    public function getNetProfitOnly(array $filters): float
    {
        $businessId = Auth::user()->business_id;

        $transactions = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->with(['details.product']);

        $this->applyDateFilters($transactions, $filters, 'transaction_date');
        $transactions = $transactions->get();

        $cogsResult = CogsCalculator::calculateForTransactions($transactions);
        $totalGrossProfit = $cogsResult['total_gross_profit'];

        $expenseQuery = $this->basePureExpenseQuery($businessId);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');

        return $totalGrossProfit - $totalExpense;
    }

    /**
     * Membangun base query beban operasional murni (is_cogs = 0).
     * Jika accounting.use_cashflow_is_cogs aktif, membaca langsung kolom cash_flow.is_cogs.
     * Jika tidak aktif (default), membaca relasi category.is_cogs.
     */
    private function basePureExpenseQuery(int $businessId)
    {
        $query = CashFlow::where('business_id', $businessId)->where('type', 'expense');

        if (config('accounting.use_cashflow_is_cogs', false)) {
            return $query->where('is_cogs', 0);
        }

        return $query->whereHas('category', function ($q) {
            $q->where('is_cogs', 0);
        });
    }

    private function applyDateFilters($query, array $filters, string $dateColumn): void
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }
    }
}
