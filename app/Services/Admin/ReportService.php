<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\Inventory;
use Carbon\Carbon;

class ReportService
{
    /**
     * Menghasilkan laporan penjualan berdasarkan filter.
     *
     * @param array $filters
     * @return array
     */
    public function getSalesReport(array $filters): array
    {
        $query = Transaction::with('customer', 'createdBy', 'details.product')
            ->where('type', 'sale');

        $this->applyDateFilters($query, $filters);

        $transactions = $query->get();
        $totalSales = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();

        return [
            'transactions' => $transactions,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'filters' => $filters,
        ];
    }

    /**
     * Menghasilkan laporan keuangan berdasarkan filter.
     *
     * @param array $filters
     * @return array
     */
    public function getFinancialReport(array $filters): array
    {
        $query = CashFlow::with('category');
        $this->applyDateFilters($query, $filters, 'date');
        
        $cashFlows = $query->get();
        $income = $cashFlows->where('type', 'income')->sum('amount');
        $expense = $cashFlows->where('type', 'expense')->sum('amount');

        return [
            'cash_flows' => $cashFlows,
            'total_income' => $income,
            'total_expense' => $expense,
            'net_profit' => $income - $expense,
            'filters' => $filters,
        ];
    }

    /**
     * Menghasilkan laporan inventaris.
     *
     * @param array $filters
     * @return array
     */
    public function getInventoryReport(array $filters): array
    {
        $inventory = Inventory::with('product')->get();
        // Logika tambahan untuk laporan inventaris bisa ditambahkan di sini
        
        return [
            'inventory' => $inventory,
            'filters' => $filters,
        ];
    }
    
    /**
     * Helper untuk menerapkan filter tanggal.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @param string $dateColumn
     * @return void
     */
    private function applyDateFilters($query, array $filters, string $dateColumn = 'transaction_date'): void
    {
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = Carbon::parse($filters['start_date'])->startOfDay();
            $endDate = Carbon::parse($filters['end_date'])->endOfDay();
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }
    }
}