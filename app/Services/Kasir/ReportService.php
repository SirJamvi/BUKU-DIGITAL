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
        $query = Transaction::where('created_by', $kasirId)->where('type', 'sale');
            
        // Terapkan filter tanggal
        $this->applyDateFilters($query, $filters);
        $transactions = $query->with('customer')->get();
        $totalSales = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();
        
        // Hitung rincian berdasarkan metode pembayaran dari data yang sudah difilter
        $salesByPaymentMethod = $transactions->groupBy('payment_method')
            ->map(function ($group) {
                return $group->sum('total_amount');
            });
        
        return [
            'transactions' => $transactions,
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'salesByPaymentMethod' => $salesByPaymentMethod,
            'filters' => $filters,
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