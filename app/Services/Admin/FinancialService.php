<?php

namespace App\Services\Admin;

use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use App\Models\CapitalTracking;
use App\Models\OwnerProfit;
use Illuminate\Pagination\LengthAwarePaginator;

class FinancialService
{
    /**
     * Mendapatkan ringkasan finansial.
     *
     * @return array
     */
    public function getFinancialSummary(): array
    {
        $income = CashFlow::where('type', 'income')->sum('amount');
        $expense = CashFlow::where('type', 'expense')->sum('amount');
        
        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net_cash_flow' => $income - $expense,
        ];
    }

    /**
     * Mendapatkan data cash flow dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCashFlowWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::with('category', 'createdBy')->latest('date')->paginate($perPage);
    }

    /**
     * Mendapatkan data pengeluaran dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getExpensesWithPagination(int $perPage = 20): LengthAwarePaginator
    {
        return CashFlow::where('type', 'expense')->with('category', 'createdBy')->latest('date')->paginate($perPage);
    }

    /**
     * Menganalisis dan mengembalikan data ROI.
     *
     * @return array
     */
    public function getRoiAnalysis(): array
    {
        $capital = CapitalTracking::first();
        if (!$capital) {
            return ['roi' => 0, 'initial_capital' => 0, 'total_profit' => 0];
        }

        $totalProfit = OwnerProfit::sum('net_profit');
        $initialCapital = $capital->initial_capital + $capital->additional_capital;

        $roi = ($initialCapital > 0) ? (($totalProfit - $initialCapital) / $initialCapital) * 100 : 0;
        
        return [
            'roi' => round($roi, 2),
            'initial_capital' => $initialCapital,
            'total_profit' => $totalProfit,
        ];
    }
}