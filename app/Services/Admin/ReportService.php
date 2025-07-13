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
     * Menghasilkan laporan keuangan komprehensif berdasarkan filter.
     * INI ADALAH FUNGSI YANG DIPERBARUI.
     */
    public function getFinancialReport(array $filters): array
    {
        $businessId = Auth::user()->business_id;

        // 1. Ambil semua transaksi penjualan dalam rentang tanggal
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->with(['details.product', 'createdBy']);
        
        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        // 2. Hitung Total Pemasukan dan Keuntungan Kotor dari transaksi
        $totalIncome = $transactions->sum('total_amount');
        $totalGrossProfit = 0;
        
        foreach ($transactions as $transaction) {
            $transactionGrossProfit = 0;
            foreach ($transaction->details as $detail) {
                if ($detail->product) { // Pastikan produk masih ada
                    $profitPerItem = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                    $transactionGrossProfit += $profitPerItem;
                }
            }
            $transaction->gross_profit = $transactionGrossProfit;
            $totalGrossProfit += $transactionGrossProfit;
        }

        // 3. Hitung Total Pengeluaran dari Cash Flow
        $expenseQuery = CashFlow::where('type', 'expense')->where('business_id', $businessId);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');
        
        // 4. Hitung Keuntungan Bersih
        $netProfit = $totalIncome - $totalExpense;

        return [
            'transactions' => $transactions, // Mengirim data transaksi yang sudah diperkaya
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'total_gross_profit' => $totalGrossProfit,
            'net_profit' => $netProfit,
            'filters' => $filters,
        ];
    }

    /**
     * Menghasilkan laporan inventaris.
     */
    public function getInventoryReport(array $filters): array
    {
        $inventory = Inventory::with('product')->where('business_id', Auth::user()->business_id)->get();
        
        return [
            'inventory' => $inventory,
            'filters' => $filters,
        ];
    }
    
    /**
     * Helper untuk menerapkan filter tanggal.
     */
    private function applyDateFilters($query, array $filters, string $dateColumn = 'transaction_date'): void
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? now()->endOfDay()->toDateString();
        
        if ($startDate && $endDate) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }
    }
}