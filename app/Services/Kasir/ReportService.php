<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Menghasilkan laporan penjualan untuk kasir tertentu.
     *
     * @param int $kasirId
     * @param array $filters
     * @return array
     */
    public function getSalesReport(int $kasirId, array $filters): array
    {
        $query = Transaction::where('created_by', $kasirId)->where('type', 'sale');
            
        // Terapkan filter tanggal jika ada
        $this->applyDateFilters($query, $filters);

        $transactions = $query->with('customer', 'details.product')->get();
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
     * Helper untuk menerapkan filter tanggal pada query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @param string $dateColumn
     * @return void
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