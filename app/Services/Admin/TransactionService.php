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
     * Membatalkan transaksi (Soft Void) beserta pemulihan stok dan pencatatan contra-entry pada cash flow.
     * Mencegah pembatalan destruktif dan mengunci transaksi jika periode bulanan sudah ditutup.
     */
    public function deleteTransaction(Transaction $transaction, ?string $reason = null): bool
    {
        // Kunci periode pembukuan bulanan
        \App\Services\Accounting\AccountingPeriodGuard::ensurePeriodOpen(
            (int) $transaction->business_id,
            $transaction->branch_id ? (int) $transaction->branch_id : null,
            $transaction->transaction_date
        );

        return DB::transaction(function () use ($transaction, $reason) {
            // 1. Rollback Stok (Kembalikan stok ke inventori dengan tipe void_return)
            foreach ($transaction->details as $detail) {
                $inventoryQuery = Inventory::withoutGlobalScope('branch')
                    ->where('product_id', $detail->product_id)
                    ->where('business_id', $transaction->business_id);
                if ($transaction->branch_id) {
                    $inventoryQuery->where('branch_id', $transaction->branch_id);
                }
                $inventory = $inventoryQuery->first();

                if ($inventory) {
                    $qtyModifier = ($transaction->type === 'sale') ? $detail->quantity : -$detail->quantity;
                    
                    $inventory->current_stock += $qtyModifier;
                    $inventory->save();

                    $movementData = [
                        'business_id'    => $transaction->business_id,
                        'branch_id'      => $transaction->branch_id,
                        'product_id'     => $detail->product_id,
                        'type'           => 'void_return',
                        'quantity'       => $qtyModifier,
                        'unit_cost'      => (float) ($detail->cost_price ?? 0),
                        'reference_type' => Transaction::class,
                        'reference_id'   => 'VOID-TX-' . $transaction->id,
                        'notes'          => 'Pembatalan & Void transaksi #' . $transaction->id . ($reason ? ": {$reason}" : ''),
                        'created_by'     => Auth::id(), 
                    ];

                    try {
                        StockMovement::create($movementData);
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Fallback ke 'adjustment' jika ENUM kolom 'type' di database belum diupdate ke 'void_return' (MySQL error 1265)
                        if (str_contains($e->getMessage(), '1265') || str_contains($e->getMessage(), 'truncated')) {
                            $movementData['type'] = 'adjustment';
                            StockMovement::create($movementData);
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            // 2. Rollback Poin Pelanggan (Jika ada poin yang diberikan)
            if ($transaction->customer_id && $transaction->points_awarded > 0) {
                $customer = Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->loyalty_points -= $transaction->points_awarded;
                    if ($customer->loyalty_points < 0) $customer->loyalty_points = 0; 
                    $customer->save();

                    DB::table('point_logs')
                        ->where('transaction_id', $transaction->id)
                        ->delete();
                }
            }

            // 3. JANGAN HAPUS record cash_flow lama!
            // Jika transaksi pernah menghasilkan penerimaan kas / non-tunai, buat jurnal pembalik (contra-entry)
            $isPaid = $transaction->payment_status === 'paid';
            if ($isPaid && (float) $transaction->total_amount > 0) {
                $isCash = strtolower($transaction->payment_method ?? '') === 'cash';
                $refundCategory = \App\Models\ExpenseCategory::where('business_id', $transaction->business_id)
                    ->where(function ($q) {
                        $q->where('name', 'LIKE', '%Refund%')
                          ->orWhere('name', 'LIKE', '%Retur%')
                          ->orWhere('name', 'LIKE', '%Pembatalan%');
                    })
                    ->first();

                $categoryId = $refundCategory?->id ?? 1;

                CashFlow::create([
                    'business_id'    => $transaction->business_id,
                    'branch_id'      => $transaction->branch_id,
                    'type'           => 'expense',
                    'flow_type'      => 'cash_refund',
                    'reference_type' => 'transaction',
                    'reference_id'   => (string) $transaction->id,
                    'category_id'    => $categoryId,
                    'amount'         => $transaction->total_amount,
                    'payment_method' => $transaction->payment_method ?: 'cash',
                    'funding_source' => $isCash ? 'drawer' : 'owner',
                    'affects_drawer' => $isCash ? 1 : 0,
                    'description'    => "Pembatalan / Void Transaksi #{$transaction->id}" . ($reason ? ": {$reason}" : ''),
                    'date'           => now()->toDateString(),
                    'created_by'     => Auth::id(),
                ]);
            }

            // 4. Soft Void: Set status cancelled dan isi audit trail
            $transaction->update([
                'status'          => 'cancelled',
                'order_status'    => 'cancelled',
                'delivery_status' => 'cancelled',
                'void_reason'     => $reason ?: 'Dibatalkan oleh Admin',
                'voided_by'       => Auth::id(),
                'voided_at'       => now(),
            ]);

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