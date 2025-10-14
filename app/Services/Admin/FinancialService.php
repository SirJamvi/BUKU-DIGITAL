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
use Carbon\Carbon;

class FinancialService
{
    /**
     * Laporan keuangan dengan perhitungan yang konsisten
     */
    public function getFinancialReport(array $filters): array
    {
        $businessId = Auth::user()->business_id;
        
        // Ambil transaksi penjualan
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->with(['details.product', 'customer', 'createdBy']);

        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        $totalIncome = 0;
        $totalGrossProfit = 0;

        foreach ($transactions as $transaction) {
            $transactionGrossProfit = 0;
            if ($transaction->details) {
                foreach ($transaction->details as $detail) {
                    if ($detail->product) {
                        $profitPerItem = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                        $transactionGrossProfit += $profitPerItem;
                    }
                }
            }
            $transaction->gross_profit = $transactionGrossProfit;
            $totalGrossProfit += $transactionGrossProfit;
            $totalIncome += $transaction->total_amount;
        }

        // Ambil total pengeluaran
        $expenseQuery = CashFlow::where('type', 'expense')->where('business_id', $businessId);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');

        $netProfit = $totalGrossProfit - $totalExpense;

        return [
            'transactions'       => $transactions,
            'total_income'       => $totalIncome,
            'total_expense'      => $totalExpense,
            'total_gross_profit' => $totalGrossProfit,
            'net_profit'         => $netProfit,
            'filters'            => $filters,
        ];
    }

    /**
     * Ringkasan finansial dashboard - Versi yang disempurnakan
     */
    public function getFinancialSummary(): array
    {
        $businessId = Auth::user()->business_id;
        
        // Total pendapatan dari penjualan
        $totalIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->sum('total_amount');
        
        // Total pengeluaran
        $totalExpense = CashFlow::where('type', 'expense')
            ->where('business_id', $businessId)
            ->sum('amount');
        
        // Hitung laba kotor
        $grossProfit = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));

        $netProfit = $grossProfit - $totalExpense;
        $netCashFlow = $totalIncome - $totalExpense;

        return [
            'total_income'     => $totalIncome,
            'total_expense'    => $totalExpense,
            'gross_profit'     => $grossProfit,
            'net_profit'       => $netProfit,
            'net_cash_flow'    => $netCashFlow,
        ];
    }

    /**
     * Analisis ROI dengan data yang akurat - Versi gabungan
     */
    public function getRoiAnalysis(): array
    {
        $businessId = Auth::user()->business_id;
        $capital = CapitalTracking::where('business_id', $businessId)->first();

        if (!$capital) {
            return $this->getAlternativeRoiAnalysis();
        }

        // Total profit dari semua periode (yang sudah dicatat)
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

    /**
     * ROI alternatif jika tidak ada data modal
     */
    private function getAlternativeRoiAnalysis(): array
    {
        $businessId = Auth::user()->business_id;
        $summary = $this->getFinancialSummary();
        $estimatedCapital = 10000000; // 10 juta sebagai estimasi

        return [
            'roi'              => ($estimatedCapital > 0) ? round(($summary['net_profit'] / $estimatedCapital) * 100, 2) : 0,
            'initial_capital'  => $estimatedCapital,
            'total_profit'     => $summary['net_profit'],
            'has_capital_data' => false,
            'data_source'      => 'cash_flow_estimate',
            'warning_message'  => 'Data modal awal belum diinput. Perhitungan ROI menggunakan estimasi.',
        ];
    }

    /**
     * Menyimpan atau memperbarui modal awal bisnis - Versi gabungan
     */
    public function storeOrUpdateCapital(array $data): void
    {
        CapitalTracking::updateOrCreate(
            ['business_id' => Auth::user()->business_id],
            [
                'initial_capital' => $data['initial_capital'],
                'additional_capital' => $data['additional_capital'] ?? 0,
                'recorded_at' => now(),
                'updated_by' => Auth::id()
            ]
        );
    }

    /**
     * Logika "Tutup Buku" yang disempurnakan - Versi gabungan dari kedua file
     */
    public function processMonthlyClosing(string $period): OwnerProfit
    {
        $businessId = Auth::user()->business_id;
        $date = Carbon::createFromFormat('Y-m', $period);
        $month = $date->month;
        $year = $date->year;

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // Hitung pendapatan bulan ini dari transaksi penjualan
        $monthlyIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // Hitung laba kotor bulan ini (Total Penjualan - HPP)
        $monthlyGrossProfit = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));

        // Hitung pengeluaran bulan ini
        $monthlyExpense = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Laba bersih = Laba kotor - Pengeluaran
        $netProfit = $monthlyGrossProfit - $monthlyExpense;

        // Simpan atau update data profit bulanan
        return OwnerProfit::updateOrCreate(
            [
                'business_id'   => $businessId,
                'period_month'  => $month,
                'period_year'   => $year
            ],
            [
                'monthly_income'    => $monthlyIncome,
                'monthly_expense'   => $monthlyExpense,
                'gross_profit'      => $monthlyGrossProfit,
                'net_profit'        => $netProfit,
                'status'            => 'pending',
                'allocated_at'      => null,
                'allocated_funds'   => 0,
                'recorded_at'       => now(),
                'updated_at'        => now()
            ]
        );
    }

    /**
     * Mendapatkan daftar periode yang tersedia untuk tutup buku
     * (bulan-bulan yang memiliki transaksi)
     */
    public function getAvailablePeriodsForClosing(): array
    {
        $businessId = Auth::user()->business_id;
        
        // Ambil semua periode dari transaksi dan pengeluaran yang ada
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

        // Gabungkan dan hapus duplikat
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
            
            // Cek apakah periode sudah di-close
            $isClosed = OwnerProfit::where('business_id', $businessId)
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->exists();

            $result[] = [
                'period' => $yearMonth,
                'label' => $monthName,
                'is_closed' => $isClosed,
                'year' => $period->year,
                'month' => $period->month,
            ];
        }

        return $result;
    }

    /**
     * Mendapatkan ringkasan tutup buku untuk periode tertentu
     * (data preview sebelum benar-benar disimpan)
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

        // Hitung pendapatan bulan ini
        $monthlyIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // Hitung laba kotor bulan ini
        $monthlyGrossProfit = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));

        // Hitung pengeluaran bulan ini
        $monthlyExpense = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Laba bersih
        $netProfit = $monthlyGrossProfit - $monthlyExpense;

        // Cek apakah sudah ada data closing untuk periode ini
        $existingClosing = OwnerProfit::where('business_id', $businessId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        return [
            'period' => $period,
            'month_name' => $monthName,
            'monthly_income' => (float) $monthlyIncome,
            'monthly_expense' => (float) $monthlyExpense,
            'gross_profit' => (float) $monthlyGrossProfit,
            'net_profit' => (float) $netProfit,
            'is_already_closed' => $existingClosing ? true : false,
            'closing_data' => $existingClosing,
            'transactions_count' => Transaction::where('business_id', $businessId)
                ->where('type', 'sale')
                ->where('status', 'completed')
                ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
                ->count(),
            'expenses_count' => CashFlow::where('business_id', $businessId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->count(),
        ];
    }

    /**
     * Ambil data profit yang belum dialokasikan
     */
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
            'profits' => $unallocatedProfits,
            'total_unallocated' => $totalUnallocated,
        ];
    }

    /**
     * Proses alokasi dana - hanya untuk profit yang belum dialokasikan
     */
    public function allocateProfits(array $allocations): void
    {
        $businessId = Auth::user()->business_id;
        
        // Ambil semua profit yang belum dialokasikan
        $unallocatedProfits = OwnerProfit::where('business_id', $businessId)
            ->where('status', 'pending')
            ->where('net_profit', '>', 0)
            ->get();

        $totalAllocated = 0;
        
        foreach ($unallocatedProfits as $profit) {
            // Tandai sebagai telah dialokasikan
            $profit->update([
                'status' => 'allocated',
                'allocated_at' => now(),
                'allocated_funds' => $profit->net_profit,
            ]);
            
            $totalAllocated += $profit->net_profit;
        }

        // Simpan riwayat alokasi ke cash flow jika diperlukan
        if ($totalAllocated > 0) {
            foreach ($allocations as $allocation) {
                if ($allocation['amount'] > 0) {
                    CashFlow::create([
                        'business_id' => $businessId,
                        'type' => 'allocation',
                        'category_id' => null,
                        'amount' => $allocation['amount'],
                        'description' => 'Alokasi Dana: ' . $allocation['description'],
                        'date' => now(),
                        'created_by' => Auth::id(),
                    ]);
                }
            }
        }
    }

    /**
     * Inisialisasi data finansial untuk bisnis baru
     */
    public function initializeBusinessFinancialData(float $initialCapital): array
    {
        $this->storeOrUpdateCapital(['initial_capital' => $initialCapital]);
        $this->processMonthlyClosing(now()->format('Y-m'));

        return [
            'message' => 'Data finansial awal berhasil diinisialisasi.',
            'initial_capital' => $initialCapital,
        ];
    }

    /**
     * Ambil data cash flow dengan pagination
     */
    public function getCashFlowWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy')
            ->latest('date')
            ->paginate($perPage);
    }

    /**
     * Ambil data pengeluaran dengan pagination - Versi yang disempurnakan
     */
    public function getExpensesWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::where('type', 'expense')
            ->where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy')
            ->latest('date')
            ->paginate($perPage);
    }

    /**
     * Ambil kategori pengeluaran - Versi yang disempurnakan
     */
    public function getExpenseCategories()
    {
        return ExpenseCategory::where('business_id', Auth::user()->business_id)->get();
    }

    /**
     * Buat pengeluaran baru
     */
    public function createExpense(array $data): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['business_id' => Auth::user()->business_id, 'name' => trim($data['category_name'])],
            ['type' => 'Operasional', 'is_active' => true, 'created_by' => Auth::id()]
        );

        CashFlow::create([
            'business_id' => Auth::user()->business_id,
            'type'        => 'expense',
            'category_id' => $category->id,
            'amount'      => $data['amount'],
            'description' => $data['description'],
            'date'        => $data['date'],
            'created_by'  => Auth::id(),
        ]);
    }

    /**
     * Memperbarui data pengeluaran yang sudah ada
     */
    public function updateExpense(CashFlow $expense, array $data): void
    {
        $category = ExpenseCategory::firstOrCreate(
            ['business_id' => Auth::user()->business_id, 'name' => trim($data['category_name'])],
            ['type' => 'Operasional', 'is_active' => true, 'created_by' => Auth::id()]
        );

        $expense->update([
            'category_id' => $category->id,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'date' => $data['date'],
        ]);
    }

    /**
     * Menghapus data pengeluaran
     */
    public function deleteExpense(CashFlow $expense): void
    {
        $expense->delete();
    }

    /**
     * Membuat Kategori Pengeluaran baru
     */
    public function createExpenseCategory(array $data): void
    {
        ExpenseCategory::firstOrCreate(
            ['business_id' => Auth::user()->business_id, 'name' => trim($data['name'])],
            ['type' => trim($data['type']), 'is_active' => true, 'created_by' => Auth::id()]
        );
    }

    /**
     * Riwayat alokasi dana
     */
    public function getAllocationHistory(): array
    {
        $businessId = Auth::user()->business_id;
        
        $allocatedProfits = OwnerProfit::where('business_id', $businessId)
            ->where('status', 'allocated')
            ->orderBy('allocated_at', 'desc')
            ->get();

        $totalAllocated = $allocatedProfits->sum('allocated_funds');

        return [
            'allocations' => $allocatedProfits,
            'total_allocated' => $totalAllocated,
        ];
    }

    /**
     * Statistik finansial bulanan
     */
    public function getMonthlyFinancialStats(int $year = null): array
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
                'month' => $month,
                'month_name' => Carbon::create()->month($month)->format('F'),
                'income' => $monthlyStats->get($month)?->monthly_income ?? 0,
                'expense' => $monthlyStats->get($month)?->monthly_expense ?? 0,
                'gross_profit' => $monthlyStats->get($month)?->gross_profit ?? 0,
                'net_profit' => $monthlyStats->get($month)?->net_profit ?? 0,
                'status' => $monthlyStats->get($month)?->status ?? 'not_processed',
            ];
        }

        return [
            'year' => $year,
            'monthly_stats' => $stats,
            'total_income' => collect($stats)->sum('income'),
            'total_expense' => collect($stats)->sum('expense'),
            'total_gross_profit' => collect($stats)->sum('gross_profit'),
            'total_net_profit' => collect($stats)->sum('net_profit'),
        ];
    }

    /**
     * Apply date filters untuk query
     */
    private function applyDateFilters($query, array $filters, string $dateColumn): void
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }
    }
}