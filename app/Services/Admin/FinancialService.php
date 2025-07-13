<?php

namespace App\Services\Admin;

use App\Models\CashFlow;
use App\Models\Transaction;
use App\Models\ExpenseCategory; // <-- Pastikan ini ada
use App\Models\CapitalTracking;
use App\Models\OwnerProfit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth; // <-- Pastikan ini ada
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialService
{
    /**
     * Menghasilkan laporan keuangan komprehensif berdasarkan filter.
     */
    public function getFinancialReport(array $filters): array
    {
        // 1. Ambil semua transaksi penjualan dalam rentang tanggal
        $transactionsQuery = Transaction::where('type', 'sale')
            ->where('business_id', Auth::user()->business_id)
            ->with(['details.product', 'customer', 'createdBy']);
        
        $this->applyDateFilters($transactionsQuery, $filters, 'transaction_date');
        $transactions = $transactionsQuery->get();

        // 2. Hitung Total Pemasukan dan Keuntungan Kotor dari transaksi
        $totalIncome = 0;
        $totalGrossProfit = 0;
        
        foreach ($transactions as $transaction) {
            $transactionGrossProfit = 0;
            foreach ($transaction->details as $detail) {
                // Keuntungan kotor per item = (harga jual - harga pokok) * kuantitas
                $profitPerItem = ($detail->product->base_price - $detail->product->cost_price) * $detail->quantity;
                $transactionGrossProfit += $profitPerItem;
            }
            $transaction->gross_profit = $transactionGrossProfit; // Tambahkan properti baru ke objek transaksi
            $totalGrossProfit += $transactionGrossProfit;
            $totalIncome += $transaction->total_amount;
        }

        // 3. Hitung Total Pengeluaran dari Cash Flow
        $expenseQuery = CashFlow::where('type', 'expense')->where('business_id', Auth::user()->business_id);
        $this->applyDateFilters($expenseQuery, $filters, 'date');
        $totalExpense = $expenseQuery->sum('amount');
        
        // 4. Hitung Keuntungan Bersih
        $netProfit = $totalIncome - $totalExpense;

        return [
            'transactions' => $transactions, // Kirim data transaksi yang sudah diperkaya
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'total_gross_profit' => $totalGrossProfit,
            'net_profit' => $netProfit,
            'filters' => $filters,
        ];
    }

    /**
     * Mendapatkan ringkasan finansial.
     *
     * @return array
     */
    public function getFinancialSummary(): array
    {
        $income = CashFlow::where('type', 'income')->sum('amount');
        $expense = CashFlow::where('type', 'expense')->sum('amount');
        
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // Menghitung Laba Kotor (Gross Profit)
        $grossProfit = DB::table('transaction_details')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->where('products.business_id', $user->business_id)
            ->sum(DB::raw('transaction_details.quantity * (products.base_price - products.cost_price)'));
        
        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net_profit' => $income - $expense,
            'gross_profit' => $grossProfit, // <-- Data Baru
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

    /**
     * Mendapatkan kategori pengeluaran berdasarkan business_id user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpenseCategories()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        return ExpenseCategory::where('business_id', $user->business_id)->get();
    }

    /**
     * Membuat data pengeluaran baru.
     * Secara otomatis membuat kategori baru jika belum ada.
     */
    public function createExpense(array $data): void
    {
        // INI BAGIAN YANG DIPERBARUI
        $category = ExpenseCategory::firstOrCreate(
            [
                'business_id' => Auth::user()->business_id,
                'name' => trim($data['category_name']) // Cari atau buat berdasarkan nama
            ],
            [
                'type' => 'Operasional', // Tipe default untuk kategori baru
                'is_active' => true,
                'created_by' => Auth::id()
            ]
        );

        CashFlow::create([
            'business_id' => Auth::user()->business_id,
            'type' => 'expense',
            'category_id' => $category->id, // Gunakan ID dari kategori yang ditemukan/dibuat
            'amount' => $data['amount'],
            'description' => $data['description'],
            'date' => $data['date'],
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Helper untuk menerapkan filter tanggal.
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