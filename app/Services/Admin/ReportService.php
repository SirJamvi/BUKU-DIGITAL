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
     * VERSI PERBAIKAN DENGAN PEMISAHAN COGS DAN OPEX
     * PERBAIKAN: HPP tidak dihitung dua kali
     */
    public function getFinancialReport(array $filters): array
    {
        $businessId = Auth::user()->business_id;
        
        // 1. Ambil total PENDAPATAN dari penjualan
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', $businessId)
            ->where('status', 'completed');
        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $totalIncome = $transactionsQuery->sum('total_amount');
        
        // Ambil data transaksi untuk ditampilkan di view
        $transactions = $transactionsQuery->with('details.product')->get();
        
        // 2. Ambil semua data arus kas (termasuk COGS dan Opex)
        $cashFlowQuery = CashFlow::where('business_id', $businessId)->with('category');
        $this->applyDateFilters($cashFlowQuery, $filters, 'date');
        $cashFlows = $cashFlowQuery->latest('date')->get();
        
        // 3. PISAHKAN PERHITUNGAN COGS DAN BEBAN OPERASIONAL
        
        // Hitung HPP/COGS: hanya dari kategori yang ditandai is_cogs = true
        $totalCogs = $cashFlows->filter(function ($flow) {
            return $flow->type === 'expense' && $flow->category && $flow->category->is_cogs;
        })->sum('amount');
        
        // ========================================================================
        // PERBAIKAN KRITIS: Hitung HANYA Beban Operasional (TANPA HPP/COGS)
        // ========================================================================
        // Beban Operasional: hanya dari kategori yang is_cogs = false atau null
        // INI MEMASTIKAN HPP TIDAK DIHITUNG DUA KALI!
        $totalExpense = $cashFlows->filter(function ($flow) {
            return $flow->type === 'expense' && (!$flow->category || $flow->category->is_cogs === false);
        })->sum('amount');
        
        // 4. HITUNG SEMUA METRIK DENGAN BENAR (SESUAI STANDAR AKUNTANSI)
        // Formula Multi-Step Income Statement:
        // Gross Profit = Revenue - COGS
        // Net Profit = Gross Profit - Operating Expenses
        $totalGrossProfit = $totalIncome - $totalCogs; // Laba Kotor = Pendapatan - HPP
        $netProfit = $totalGrossProfit - $totalExpense; // Laba Bersih = Laba Kotor - Beban Operasional (TANPA HPP)
        
        return [
            'transactions'       => $transactions,
            'cash_flows'         => $cashFlows, // Semua cash flows untuk detail tabel
            'total_income'       => $totalIncome,
            'total_cogs'         => $totalCogs, // HPP/COGS (Cost of Goods Sold)
            'total_expense'      => $totalExpense, // HANYA Beban Operasional (NO COGS!)
            'total_gross_profit' => $totalGrossProfit,
            'net_profit'         => $netProfit, // Hasil yang benar ✅
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