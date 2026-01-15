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
                ->map(fn($group) => $group->sum('total_amount'));
            
            // Notifikasi item dengan stok rendah
            $lowStockItems = Inventory::whereColumn('current_stock', '<=', 'min_stock')->count();
            
            // [BARU] Hitung jumlah produk terjual hari ini oleh kasir ini
            $productsSoldToday = DB::table('transactions')
                ->join('transaction_details', 'transactions.id', '=', 'transaction_details.transaction_id')
                ->join('products', 'transaction_details.product_id', '=', 'products.id')
                ->where('transactions.created_by', $kasirId)
                ->whereDate('transactions.transaction_date', $today)
                ->select('products.name', DB::raw('SUM(transaction_details.quantity) as total_quantity_sold'))
                ->groupBy('products.name')
                ->orderByDesc('total_quantity_sold')
                ->limit(5) // Ambil 5 produk terlaris
                ->get();
            
            return [
                'mySalesToday'         => $mySalesToday,
                'myTransactionsToday'  => $myTransactionsToday,
                'lowStockItems'        => $lowStockItems,
                'salesByPaymentMethod' => $salesByPaymentMethod,
                'productsSoldToday'    => $productsSoldToday, // Data baru
            ];
        } catch (\Exception $e) {
            logger()->error(__METHOD__ . ': ' . $e->getMessage());
            
            // Mengembalikan nilai default jika terjadi error
            return [
                'mySalesToday'         => 0,
                'myTransactionsToday'  => 0,
                'lowStockItems'        => 0,
                'salesByPaymentMethod' => collect(),
                'productsSoldToday'    => collect(), // Koleksi kosong untuk data baru
            ];
        }
    }
}