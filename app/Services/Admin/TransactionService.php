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
     * Mendapatkan semua transaksi dengan paginasi dan filter pencarian untuk admin.
     */
    public function getAllTransactionsWithPagination(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Transaction::with(['customer', 'createdBy', 'driver'])
            ->latest('transaction_date');

        // Pencarian berdasarkan ID, Pelanggan, Kasir, Driver, atau No. Surat Jalan
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $isExplicitId = str_starts_with($search, '#') || 
                            stripos($search, 'trx-') === 0 || 
                            stripos($search, 'tx-') === 0;

            $numericOnly = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $isExplicitId, $numericOnly) {
                if ($isExplicitId && !empty($numericOnly)) {
                    // Pencarian eksplisit ID (misal #123 atau TRX-123)
                    $q->where('id', (int) $numericOnly);
                } elseif (ctype_digit($search)) {
                    // Murni angka (misal '12') -> cocokan ID persis, atau ID yang mengandung angka, atau no telepon pelanggan
                    $q->where('id', (int) $search)
                      ->orWhere('id', 'LIKE', "%{$search}%")
                      ->orWhere('delivery_trip_code', 'LIKE', "%{$search}%")
                      ->orWhereHas('customer', function ($cq) use ($search) {
                          $cq->where('phone', 'LIKE', "%{$search}%");
                      });
                } else {
                    // Pencarian teks umum (nama customer, kasir, driver, trip code, notes)
                    $q->where('delivery_trip_code', 'LIKE', "%{$search}%")
                      ->orWhere('notes', 'LIKE', "%{$search}%")
                      ->orWhereHas('customer', function ($cq) use ($search) {
                          $cq->where('name', 'LIKE', "%{$search}%")
                             ->orWhere('phone', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('createdBy', function ($uq) use ($search) {
                          $uq->where('name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('driver', function ($dq) use ($search) {
                          $dq->where('name', 'LIKE', "%{$search}%");
                      });

                    // Jika di dalam teks ada angka, dukung juga pencarian ID
                    if (!empty($numericOnly) && strlen($numericOnly) <= 8) {
                        $q->orWhere('id', (int) $numericOnly);
                    }
                }
            });
        }

        // Filter status pembayaran (paid / pending)
        if (!empty($filters['payment_status']) && in_array($filters['payment_status'], ['paid', 'pending'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        // Filter tanggal mulai
        if (!empty($filters['start_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }

        // Filter tanggal akhir
        if (!empty($filters['end_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage)->withQueryString();
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
            CashFlow::where('business_id', $transaction->business_id)
                ->where(function ($q) use ($transaction) {
                    $q->where('reference_id', (string) $transaction->id)
                      ->orWhere('reference_id', 'TX-' . $transaction->id);
                })
                ->delete();

            // 4. Hapus Data Transaksi
            $transaction->delete();

            return true;
        });
    }

    /**
     * Memperbarui transaksi melalui KasirTransactionService agar konsisten.
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        return app(\App\Services\Kasir\TransactionService::class)->updateTransaction($transaction, $data);
    }
}