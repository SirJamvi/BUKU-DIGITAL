<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\CashFlow;
use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // <-- Tambahkan Auth untuk perbaikan Intelephense

class TransactionService
{
    /**
     * Mendapatkan semua transaksi dengan paginasi untuk admin.
     */
    public function getAllTransactionsWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::with(['customer', 'createdBy'])
            ->latest('transaction_date')
            ->paginate($perPage);
    }

    /**
     * Menghapus transaksi secara permanen beserta pengembalian stok, kas, dan poin.
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        return DB::transaction(function () use ($transaction) {
            // 1. Rollback Stok (Kembalikan stok ke inventori)
            foreach ($transaction->details as $detail) {
                $inventory = Inventory::where('product_id', $detail->product_id)
                    ->where('business_id', $transaction->business_id)
                    ->first();

                if ($inventory) {
                    $qtyModifier = ($transaction->type === 'sale') ? $detail->quantity : -$detail->quantity;
                    
                    $inventory->current_stock += $qtyModifier;
                    $inventory->save();

                    // Perbaikan: Gunakan Auth::id() menggantikan auth()->id()
                    StockMovement::create([
                        'business_id'  => $transaction->business_id,
                        'product_id'   => $detail->product_id,
                        'type'         => 'adjustment',
                        'quantity'     => $qtyModifier,
                        'reference_id' => 'CANCEL-TX-' . $transaction->id,
                        'notes'        => 'Pembatalan & Hapus transaksi #' . $transaction->id,
                        'created_by'   => Auth::id(), 
                    ]);
                }
            }

            // 2. Rollback Poin Pelanggan (Jika ada poin yang diberikan)
            if ($transaction->customer_id && $transaction->points_awarded > 0) {
                $customer = Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->loyalty_points -= $transaction->points_awarded;
                    if ($customer->loyalty_points < 0) $customer->loyalty_points = 0; 
                    $customer->save();

                    // Perbaikan: Gunakan DB::table karena model PointLog belum ada
                    DB::table('point_logs')
                        ->where('transaction_id', $transaction->id)
                        ->delete();
                }
            }

            // 3. Hapus Arus Kas (Cash Flow) yang terikat dengan transaksi ini
            CashFlow::where('reference_id', $transaction->id)
                ->orWhere('reference_id', 'TX-' . $transaction->id) 
                ->delete();

            // 4. Hapus Data Transaksi
            $transaction->delete();

            return true;
        });
    }
}