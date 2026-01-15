<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Menghasilkan laporan penjualan untuk kasir tertentu.
     */
    public function getSalesReport(int $kasirId, array $filters): array
    {
        // Query dasar untuk transaksi
        $baseQuery = Transaction::where('transactions.created_by', $kasirId) // <-- [FIX] Ditambahkan prefix 'transactions.'
                                ->where('type', 'sale');
        
        // Terapkan filter tanggal pada query dasar
        $this->applyDateFilters($baseQuery, $filters);

        // Klon query untuk kalkulasi yang berbeda agar tidak saling mempengaruhi
        $transactionsQuery = clone $baseQuery;
        $productsQuery = clone $baseQuery;

        // Ambil data transaksi lengkap
        $transactions = $transactionsQuery->with('customer')->get();
        $totalSales = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();

        // Hitung rincian berdasarkan metode pembayaran
        $salesByPaymentMethod = $transactions->groupBy('payment_method')
            ->map(fn($group) => $group->sum('total_amount'));
        
        // Hitung produk terlaris
        $topSoldProducts = $productsQuery
            ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
            ->join('products', 'transaction_details.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_quantity_sold'))
            ->groupBy('products.name')
            ->orderByDesc('total_quantity_sold')
            ->limit(5)
            ->get();

        return [
            'transactions'         => $transactions,
            'total_sales'          => $totalSales,
            'total_transactions'   => $totalTransactions,
            'salesByPaymentMethod' => $salesByPaymentMethod,
            'topSoldProducts'      => $topSoldProducts,
            'filters'              => $filters,
        ];
    }

    /**
     * Helper untuk menerapkan filter tanggal pada query.
     */
    private function applyDateFilters($query, array $filters, string $dateColumn = 'transaction_date'): void
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        if ($startDate && $endDate) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        } elseif ($startDate) {
            $query->where($dateColumn, '>=', Carbon::parse($startDate)->startOfDay());
        } elseif ($endDate) {
            $query->where($dateColumn, '<=', Carbon::parse($endDate)->endOfDay());
        }
    }
}