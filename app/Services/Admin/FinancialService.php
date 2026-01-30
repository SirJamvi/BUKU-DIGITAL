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
use Illuminate\Support\Facades\Log;

class FinancialService
{
    /**
     * Laporan keuangan dengan perhitungan yang konsisten
     * PERBAIKAN: HPP dan Beban Operasional dihitung dengan benar
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

        // Ambil semua cash flows
        $cashFlowQuery = CashFlow::where('business_id', Auth::user()->business_id)->with('category');
        $this->applyDateFilters($cashFlowQuery, $filters, 'date');
        $cashFlows = $cashFlowQuery->latest('date')->get();

        // ========================================================================
        // ✅ PERBAIKAN KRITIS: Pisahkan COGS dan Operating Expenses dengan BENAR
        // ========================================================================
        
        // ✅ Hitung HPP/COGS: hanya dari kategori yang ditandai is_cogs = 1 (true)
        $totalCogs = $cashFlows->filter(function ($flow) {
            return $flow->type === 'expense' && $flow->category && $flow->category->is_cogs == 1;
        })->sum('amount');
        
        // ✅ Hitung HANYA Beban Operasional (is_cogs == 0)
        $totalExpense = $cashFlows->filter(function ($flow) {
            return $flow->type === 'expense' && $flow->category && $flow->category->is_cogs == 0;
        })->sum('amount');

        // Hitung laba bersih dengan benar
        // Net Profit = Gross Profit - Operating Expenses (NO COGS!)
        $netProfit = $totalGrossProfit - $totalExpense;

        // ========== DEBUGGING CODE ==========
        Log::info('=== FINANCIAL REPORT DEBUG ===');
        Log::info('Business ID: ' . Auth::user()->business_id);
        Log::info('Total Cash Flows: ' . $cashFlows->count());
        Log::info('Cash Flows with expense type: ' . $cashFlows->where('type', 'expense')->count());
        
        // Debug kategori
        $expenseFlows = $cashFlows->where('type', 'expense');
        foreach ($expenseFlows as $flow) {
            Log::info('Flow ID: ' . $flow->id .
                        ' | Amount: ' . $flow->amount .
                        ' | Category: ' . ($flow->category ? $flow->category->name : 'NULL') .
                        ' | is_cogs: ' . ($flow->category ? ($flow->category->is_cogs ? 'true' : 'false') : 'N/A'));
        }
        
        Log::info('Total COGS: ' . $totalCogs);
        Log::info('Total Expense: ' . $totalExpense);
        Log::info('Total Gross Profit: ' . $totalGrossProfit);
        Log::info('Net Profit: ' . $netProfit);
        Log::info('===========================');
        // ====================================

        return [
            'transactions'       => $transactions,
            'cash_flows'         => $cashFlows,
            'total_income'       => $totalIncome,
            'total_cogs'         => $totalCogs,
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
        
        // Total pengeluaran (HANYA Operasional, bukan COGS)
        $totalExpense = CashFlow::where('type', 'expense')
            ->where('business_id', $businessId)
            ->whereHas('category', function($q) {
                $q->where('is_cogs', 0);
            })
            ->sum('amount');
        
        // Hitung laba kotor dengan loop untuk keamanan
        $grossProfit = 0;
        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->with('details.product')
            ->get();
            
        foreach ($transactions as $transaction) {
            foreach ($transaction->details as $detail) {
                if ($detail->product && $detail->product->base_price && $detail->product->cost_price) {
                    $grossProfit += ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                }
            }
        }

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
     * Logika "Tutup Buku" yang disempurnakan
     * PERBAIKAN: Menggunakan perhitungan manual yang sama dengan laporan keuangan
     */
    public function processMonthlyClosing(string $period): OwnerProfit
    {
        $businessId = Auth::user()->business_id;
        $date = Carbon::createFromFormat('Y-m', $period);
        $month = $date->month;
        $year = $date->year;

        // =======================================================
        // PENGECEKAN: JANGAN PROSES JIKA SUDAH SELESAI
        // =======================================================
        $existingProfit = OwnerProfit::where('business_id', $businessId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        if ($existingProfit && $existingProfit->status === 'completed') {
            return $existingProfit;
        }
        // =======================================================

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 1. Hitung pendapatan bulan ini dari transaksi penjualan
        $monthlyIncome = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // ====================================================================
        // 2. Hitung laba kotor bulan ini DENGAN LOOP MANUAL (lebih aman)
        // ====================================================================
        $monthlyGrossProfit = 0;
        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with(['details.product'])
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->details) {
                foreach ($transaction->details as $detail) {
                    if ($detail->product && $detail->product->base_price && $detail->product->cost_price) {
                        $profitPerItem = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                        $monthlyGrossProfit += $profitPerItem;
                    }
                }
            }
        }

        // ====================================================================
        // 3. Hitung Beban Operasional (HANYA Opex, BUKAN HPP)
        // ====================================================================
        $monthlyOpex = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereHas('category', function ($query) {
                $query->where('is_cogs', 0); // Hanya ambil kategori yang BUKAN HPP
            })
            ->sum('amount');
        
        // 4. Hitung Laba Bersih yang BENAR
        $netProfit = $monthlyGrossProfit - $monthlyOpex; // Laba Kotor - Beban Operasional
        
        // ========================================
        // 🔍 DEBUGGING CODE
        // ========================================
        Log::info('=== MONTHLY CLOSING DEBUG ===');
        Log::info('Period: ' . $period);
        Log::info('Business ID: ' . $businessId);
        Log::info('Date Range: ' . $startOfMonth . ' to ' . $endOfMonth);
        Log::info('Monthly Income: ' . $monthlyIncome);
        Log::info('Monthly Gross Profit (manual loop): ' . $monthlyGrossProfit);
        Log::info('Monthly Opex (is_cogs=0): ' . $monthlyOpex);
        Log::info('Calculated Net Profit: ' . $netProfit);
        
        // Debug detail transaksi
        Log::info('--- Transaction Details ---');
        foreach ($transactions as $transaction) {
            Log::info('Transaction ID: ' . $transaction->id . ' | Total: ' . $transaction->total_amount);
            foreach ($transaction->details as $detail) {
                if ($detail->product) {
                    $profit = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                    Log::info('  Product: ' . $detail->product->name . 
                              ' | Base: ' . $detail->product->base_price . 
                              ' | Cost: ' . $detail->product->cost_price . 
                              ' | Qty: ' . $detail->quantity . 
                              ' | Profit: ' . $profit);
                } else {
                    Log::warning('  Detail ID ' . $detail->id . ' - NO PRODUCT!');
                }
            }
        }
        
        // Debug pengeluaran operasional
        Log::info('--- Operational Expenses ---');
        $opexItems = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereHas('category', function ($query) {
                $query->where('is_cogs', 0);
            })
            ->with('category')
            ->get();
            
        foreach ($opexItems as $item) {
            Log::info('Expense ID: ' . $item->id . 
                      ' | Amount: ' . $item->amount . 
                      ' | Category: ' . ($item->category ? $item->category->name : 'NULL') .
                      ' | is_cogs: ' . ($item->category ? $item->category->is_cogs : 'N/A'));
        }
        
        Log::info('===========================');
        // ========================================

        // Simpan atau update data profit bulanan
        return OwnerProfit::updateOrCreate(
            [
                'business_id'  => $businessId,
                'period_month'  => $month,
                'period_year'   => $year
            ],
            [
                'monthly_income'    => $monthlyIncome,
                'monthly_expense'   => $monthlyOpex,
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

        // Hitung laba kotor bulan ini (manual loop)
        $monthlyGrossProfit = 0;
        $transactions = Transaction::where('business_id', $businessId)
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->with(['details.product'])
            ->get();

        foreach ($transactions as $transaction) {
            foreach ($transaction->details as $detail) {
                if ($detail->product && $detail->product->base_price && $detail->product->cost_price) {
                    $monthlyGrossProfit += ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                }
            }
        }

        // Hitung pengeluaran bulan ini (hanya Opex, is_cogs == 0)
        $monthlyOpex = CashFlow::where('business_id', $businessId)
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereHas('category', function ($query) {
                $query->where('is_cogs', 0);
            })
            ->sum('amount');

        // Laba bersih
        $netProfit = $monthlyGrossProfit - $monthlyOpex;

        // Cek apakah sudah ada data closing untuk periode ini
        $existingClosing = OwnerProfit::where('business_id', $businessId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        return [
            'period' => $period,
            'month_name' => $monthName,
            'monthly_income' => (float) $monthlyIncome,
            'monthly_expense' => (float) $monthlyOpex,
            'gross_profit' => (float) $monthlyGrossProfit,
            'net_profit' => (float) $netProfit,
            'is_already_closed' => $existingClosing ? true : false,
            'closing_data' => $existingClosing,
            'transactions_count' => $transactions->count(),
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
    public function getExpensesWithPagination(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CashFlow::where('type', 'expense')
            ->where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy');

        // 1. Logika Filter Kategori
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // 2. Logika Filter Tanggal
        $this->applyDateFilters($query, $filters, 'date');

        return $query->latest('date')->paginate($perPage);
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
            ['type' => 'Operasional', 'is_active' => true, 'created_by' => Auth::id(), 'is_cogs' => false]
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
            [
                'type' => 'Operasional', 
                'is_active' => true, 
                'created_by' => Auth::id(),
                'is_cogs' => false
            ]
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
            [
                'type' => $data['type'] ?? 'Operasional',
                'is_active' => true,
                'created_by' => Auth::id(),
                'is_cogs' => $data['is_cogs'] ?? false,
            ]
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