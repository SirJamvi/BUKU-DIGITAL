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
    public function getFinancialReport(array $filters): array
    {
        $businessId = Auth::user()->business_id;
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->with(['details.product', 'customer', 'createdBy']);

        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        $totalIncome = 0;
        $totalGrossProfit = 0;

        foreach ($transactions as $transaction) {
            $transactionGrossProfit = 0;
            foreach ($transaction->details as $detail) {
                $profitPerItem = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                $transactionGrossProfit += $profitPerItem;
            }
            $transaction->gross_profit = $transactionGrossProfit;
            $totalGrossProfit += $transactionGrossProfit;
            $totalIncome += $transaction->total_amount;
        }

        $expenseQuery = CashFlow::where('type', 'expense')->where('business_id', $businessId);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');

        $netProfit = $totalIncome - $totalExpense;

        return [
            'transactions'       => $transactions,
            'total_income'       => $totalIncome,
            'total_expense'      => $totalExpense,
            'total_gross_profit' => $totalGrossProfit,
            'net_profit'         => $netProfit,
            'filters'            => $filters,
        ];
    }

    public function getFinancialSummary(): array
    {
        $businessId = Auth::user()->business_id;
        $income = CashFlow::where('type', 'income')->where('business_id', $businessId)->sum('amount');
        $expense = CashFlow::where('type', 'expense')->where('business_id', $businessId)->sum('amount');
        $grossProfit = DB::table('transaction_details')
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', 'sale')
            ->where('transactions.status', 'completed')
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));
        $netCashFlow = $income - $expense;

        return [
            'total_income'  => $income,
            'total_expense' => $expense,
            'net_cash_flow' => $netCashFlow,
            'net_profit'    => $netCashFlow,
            'gross_profit'  => $grossProfit,
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

    public function getRoiAnalysis(): array
    {
        $businessId = Auth::user()->business_id;
        $capital = CapitalTracking::where('business_id', $businessId)->first();
        if (!$capital) {
            return $this->getAlternativeRoiAnalysis();
        }

        $totalProfit = OwnerProfit::where('business_id', $businessId)->sum('net_profit');
        if ($totalProfit == 0) {
            $totalProfit = $this->calculateAlternativeProfit();
        }

        $initialCapital = $capital->initial_capital + $capital->additional_capital;
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
        $businessId = Auth::user()->business_id;
        $totalIncome = CashFlow::where('type', 'income')->where('business_id', $businessId)->sum('amount');
        $totalExpense = CashFlow::where('type', 'expense')->where('business_id', $businessId)->sum('amount');
        $netProfit = $totalIncome - $totalExpense;
        $estimatedCapital = $this->estimateInitialCapital();
        $roi = ($estimatedCapital > 0) ? ($netProfit / $estimatedCapital) * 100 : 0;

        return [
            'roi'              => round($roi, 2),
            'initial_capital'  => $estimatedCapital,
            'total_profit'     => $netProfit,
            'has_capital_data' => false,
            'data_source'      => 'cash_flow_estimate',
            'warning_message'  => 'Data modal awal belum diinput. Perhitungan ROI menggunakan estimasi.',
        ];
    }

    private function estimateInitialCapital(): float
    {
        $businessId = Auth::user()->business_id;
        $firstExpense = CashFlow::where('type', 'expense')
            ->where('business_id', $businessId)
            ->oldest('date')
            ->first();
        if ($firstExpense) {
            return CashFlow::where('type', 'expense')
                ->where('business_id', $businessId)
                ->whereMonth('date', $firstExpense->date)
                ->sum('amount') * 10;
        }

        $totalProductValue = DB::table('products')
            ->where('business_id', $businessId)
            ->sum(DB::raw('cost_price * stock_quantity'));

        return $totalProductValue > 0 ? $totalProductValue : 10000000;
    }

    private function calculateAlternativeProfit(): float
    {
        $businessId = Auth::user()->business_id;
        $income = CashFlow::where('type', 'income')->where('business_id', $businessId)->sum('amount');
        $expense = CashFlow::where('type', 'expense')->where('business_id', $businessId)->sum('amount');

        return $income - $expense;
    }

    public function createDefaultCapitalTracking(float $initialCapital): void
    {
        $businessId = Auth::user()->business_id;
        $existingCapital = CapitalTracking::where('business_id', $businessId)->first();
        if (!$existingCapital) {
            CapitalTracking::create([
                'business_id'      => $businessId,
                'initial_capital'  => $initialCapital,
                'additional_capital' => 0,
                'total_returned'   => 0,
                'status'           => 'active',
                'recorded_at'      => now(),
                'updated_by'       => Auth::id(),
            ]);
        }
    }

    public function initializeBusinessFinancialData(float $initialCapital = 10000000): array
    {
        $businessId = Auth::user()->business_id;
        $this->createDefaultCapitalTracking($initialCapital);

        $currentMonth = now()->month;
        $currentYear = now()->year;
        $ownerProfit = OwnerProfit::where('business_id', $businessId)
            ->where('period_month', $currentMonth)
            ->where('period_year', $currentYear)
            ->first();

        if (!$ownerProfit) {
            $currentProfit = $this->getCurrentMonthNetProfit();
            OwnerProfit::create([
                'business_id'   => $businessId,
                'period_month'  => $currentMonth,
                'period_year'   => $currentYear,
                'net_profit'    => $currentProfit,
                'status'        => 'pending',
                'recorded_at'   => now(),
            ]);
        }

        return [
            'message'          => 'Data finansial awal berhasil diinisialisasi',
            'initial_capital'  => $initialCapital,
            'current_profit'   => $currentProfit ?? 0,
        ];
    }

    public function getExpenseCategories()
    {
        return ExpenseCategory::where('business_id', Auth::user()->business_id)->get();
    }

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

    public function createExpenseCategory(array $data): void
    {
        ExpenseCategory::firstOrCreate(
            ['business_id' => Auth::user()->business_id, 'name' => trim($data['name'])],
            ['type'        => trim($data['type']), 'is_active' => true, 'created_by' => Auth::id()]
        );
    }

    public function getCurrentMonthNetProfit(): float
    {
        $businessId = Auth::user()->business_id;
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $ownerProfit = OwnerProfit::where('business_id', $businessId)
            ->where('period_month', $currentMonth)
            ->where('period_year', $currentYear)
            ->first();

        if ($ownerProfit) {
            return $ownerProfit->net_profit;
        }

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyIncome = CashFlow::where('type', 'income')
            ->where('business_id', $businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyExpense = CashFlow::where('type', 'expense')
            ->where('business_id', $businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return $monthlyIncome - $monthlyExpense;
    }

    public function processMonthlyClosing(string $period): void
    {
        $businessId = Auth::user()->business_id;
        $date = Carbon::createFromFormat('Y-m', $period);
        $month = $date->month;
        $year = $date->year;
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $monthlyIncome = CashFlow::where('business_id', $businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $monthlyExpense = CashFlow::where('business_id', $businessId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $netProfit = $monthlyIncome - $monthlyExpense;
        // [FIX] Menggunakan updateOrCreate dengan lebih eksplisit
        // untuk memastikan status selalu kembali ke 'pending' saat sinkronisasi
        OwnerProfit::updateOrCreate(
            [
                'business_id'   => $businessId, 
                'period_month'  => $month, 
                'period_year'   => $year
            ],
            [
                'net_profit'    => $netProfit, 
                'status'        => 'pending', // Paksa status kembali ke pending
                'allocated_at'  => null, // Hapus tanggal alokasi sebelumnya
                'allocated_funds' => 0, // Reset dana yang teralokasi
                'updated_at'    => now()
            ]
        );
    }

    private function applyDateFilters($query, array $filters, string $dateColumn = 'transaction_date'): void
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }
    }
}