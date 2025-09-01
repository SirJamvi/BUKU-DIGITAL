<?php
namespace App\Services\Kasir;
use App\Models\Transaction;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Mengambil data untuk dashboard kasir.
     */
    public function getDashboardData(int $kasirId): array
    {
        try {
            $today = Carbon::today();
            
            // Ambil semua transaksi hari ini oleh kasir
            $transactionsToday = Transaction::where('created_by', $kasirId)
                ->where('type', 'sale')
                ->whereDate('transaction_date', $today)
                ->get();
            
            // Hitung total penjualan & transaksi
            $mySalesToday = $transactionsToday->sum('total_amount');
            $myTransactionsToday = $transactionsToday->count();
            
            // Hitung rincian berdasarkan metode pembayaran
            $salesByPaymentMethod = $transactionsToday->groupBy('payment_method')
                ->map(function ($group) {
                    return $group->sum('total_amount');
                });
            
            // Notifikasi item dengan stok rendah
            $lowStockItems = Inventory::whereColumn('current_stock', '<=', 'min_stock')->count();
            
            return [
                'mySalesToday' => $mySalesToday,
                'myTransactionsToday' => $myTransactionsToday,
                'lowStockItems' => $lowStockItems,
                'salesByPaymentMethod' => $salesByPaymentMethod,
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());
            return [
                'mySalesToday' => 0,
                'myTransactionsToday' => 0,
                'lowStockItems' => 0,
                'salesByPaymentMethod' => collect(),
            ];
        }
    }
}