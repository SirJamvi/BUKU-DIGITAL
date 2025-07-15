<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Menghasilkan laporan penjualan berdasarkan filter.
     */
    public function getSalesReport(array $filters): array
    {
        $query = Transaction::with('customer', 'createdBy', 'details.product')
            ->where('type', 'sale')
            ->where('business_id', Auth::user()->business_id);

        $this->applyDateFilters($query, $filters, 'transaction_date');

        $transactions = $query->latest('transaction_date')->get();
        $totalSales = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();

        return [
            'transactions'       => $transactions,
            'total_sales'        => $totalSales,
            'total_transactions' => $totalTransactions,
            'filters'            => $filters,
        ];
    }

    /**
     * Menghasilkan laporan keuangan komprehensif berdasarkan filter.
     */
    public function getFinancialReport(array $filters): array
    {
        $businessId = Auth::user()->business_id;

        // 1. Ambil data arus kas (semua pemasukan & pengeluaran)
        $cashFlowQuery = CashFlow::where('business_id', $businessId)->with('category');
        $this->applyDateFilters($cashFlowQuery, $filters, 'date');
        $cashFlows = $cashFlowQuery->latest('date')->get();

        // 2. Ambil data transaksi penjualan untuk menghitung laba kotor
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->with('details.product');
        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        // 3. Hitung semua metrik
        $totalIncome      = $cashFlows->where('type', 'income')->sum('amount');
        $totalExpense     = $cashFlows->where('type', 'expense')->sum('amount');
        $totalGrossProfit = $transactions->reduce(function ($carry, $transaction) {
            return $carry + $transaction->details->reduce(function ($itemCarry, $detail) {
                if ($detail->product) {
                    return $itemCarry + (
                        ($detail->product->base_price - $detail->product->cost_price)
                        * $detail->quantity
                    );
                }
                return $itemCarry;
            }, 0);
        }, 0);

        $netProfit = $totalGrossProfit - $totalExpense;

        return [
            'transactions'       => $transactions,
            'cash_flows'         => $cashFlows,
            'total_income'       => $totalIncome,
            'total_expense'      => $totalExpense,
            'total_gross_profit' => $totalGrossProfit,
            'net_profit'         => $netProfit,
            'filters'            => $filters,
        ];
    }

    /**
     * Menghasilkan laporan inventaris.
     */
    public function getInventoryReport(array $filters): array
    {
        return [
            'inventory' => Inventory::with('product')->get(),
            'filters'   => $filters,
        ];
    }

    /**
     * Helper untuk menerapkan filter tanggal.
     */
    private function applyDateFilters($query, array $filters, string $dateColumn): void
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $filters['end_date']   ?? now()->endOfDay()->toDateString();

        if ($startDate && $endDate) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }
    }
}
