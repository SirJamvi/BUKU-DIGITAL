<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\CashFlow;
use App\Services\Kasir\PosService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Mendapatkan riwayat transaksi berdasarkan ID kasir untuk bisnis saat ini.
     */
    public function getTransactionsByKasir(int $kasirId, int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::where('created_by', $kasirId)
            ->with('customer')
            ->latest('transaction_date')
            ->paginate($perPage);
    }

    /**
     * Mendapatkan detail lengkap dari sebuah transaksi.
     */
    public function getTransactionDetails(Transaction $transaction): array
    {
        $transaction->load('customer', 'createdBy', 'details.product');

        return [
            'transaction' => $transaction,
        ];
    }

    /**
     * [UPDATE] Mengambil data yang diperlukan untuk halaman edit transaksi.
     */
    public function getEditTransactionData(Transaction $transaction): array
    {
        // Ambil data produk dan customer
        $products = Product::where('business_id', $transaction->business_id)
            ->where('is_active', true)
            ->get();

        $customers = Customer::where('business_id', $transaction->business_id)
            ->where('status', 'active')
            ->get();

        // [BARU] Ambil Metode Pembayaran dari database
        $paymentMethods = PaymentMethod::where('business_id', $transaction->business_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'transaction'    => $transaction->load('details.product'),
            'products'       => $products,
            'customers'      => $customers,
            'paymentMethods' => $paymentMethods, // [BARU] Kirim ke view
        ];
    }

    /**
     * Logika inti untuk memperbarui transaksi dan menyinkronkan CashFlow.
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            // Kumpulkan snapshot lama per product_id menurut urutan id detail (FIFO queue)
            $oldSnapshotsByProduct = [];
            foreach ($transaction->details()->orderBy('id')->get() as $oldDetail) {
                $oldSnapshotsByProduct[$oldDetail->product_id][] = (float) ($oldDetail->cost_price ?? 0);
            }

            // 1. Kembalikan stok dari item-item lama ke inventaris
            foreach ($transaction->details as $oldDetail) {
                if ($oldDetail->product && $oldDetail->product->inventory) {
                    $oldDetail->product->inventory->increment('current_stock', $oldDetail->quantity);
                }
            }
            // Hapus detail transaksi yang lama
            $transaction->details()->delete();

            // 2. Proses item baru: kurangi stok & buat detail baru
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Pastikan stok cukup untuk item baru
                if ($product->inventory->current_stock < $item['quantity']) {
                    throw new \Exception("Stok untuk produk '{$product->name}' tidak mencukupi.");
                }

                // Kurangi stok inventaris
                $product->inventory->decrement('current_stock', $item['quantity']);

                // Snapshot cost_price:
                // - Produk jasa = 0
                // - Antrian snapshot lama jika ada untuk product_id ini
                // - Item baru memakai master berjalan
                if ($product->isService()) {
                    $snapshotCost = 0.0;
                } elseif (!empty($oldSnapshotsByProduct[$product->id])) {
                    $snapshotCost = array_shift($oldSnapshotsByProduct[$product->id]);
                } else {
                    $snapshotCost = (float) ($product->cost_price ?? 0);
                }

                $item['cost_price'] = $snapshotCost;
                $transaction->details()->create($item);
            }

            // 3. Perbarui data transaksi utama
            $paymentMethod = $data['payment_method'];
            $isKasbon = strtolower($paymentMethod) === 'kasbon' || (($data['payment_status'] ?? '') === 'pending');

            $updatePayload = [
                'total_amount'    => $data['total_amount'],
                'customer_id'     => $data['customer_id'] ?? null,
                'payment_method'  => $paymentMethod,
                'notes'           => $data['notes'] ?? null,
            ];

            if ($isKasbon) {
                $updatePayload['payment_status'] = 'pending';
            } elseif (isset($data['payment_status'])) {
                $updatePayload['payment_status'] = $data['payment_status'];
            }

            $transaction->update($updatePayload);

            // 4. [BARU] Sinkronisasi Arus Kas (CashFlow)
            // Jika statusnya pending / kasbon, hapus record CashFlow pemasukannya
            if ($transaction->payment_status === 'pending' || $isKasbon) {
                CashFlow::where('business_id', $transaction->business_id)
                    ->where(function ($q) use ($transaction) {
                        $q->where('reference_id', (string) $transaction->id)
                          ->orWhere('reference_id', 'TX-' . $transaction->id);
                    })
                    ->where('type', 'income')
                    ->delete();
            } else {
                // Jika lunas (paid), sinkronkan nominal & payment_method di cash_flow
                $cashFlow = CashFlow::where('business_id', $transaction->business_id)
                    ->where(function ($q) use ($transaction) {
                        $q->where('reference_id', (string) $transaction->id)
                          ->orWhere('reference_id', 'TX-' . $transaction->id);
                    })
                    ->where('type', 'income')
                    ->first();

                if ($cashFlow) {
                    $cashFlow->update([
                        'amount'         => $transaction->total_amount,
                        'payment_method' => $transaction->payment_method,
                    ]);
                } else {
                    // Jika sebelumnya pending lalu diubah menjadi lunas, buatkan cash_flow
                    CashFlow::create([
                        'business_id'    => $transaction->business_id,
                        'type'           => 'income',
                        'category_id'    => 1,
                        'payment_method' => $transaction->payment_method,
                        'amount'         => $transaction->total_amount,
                        'description'    => 'Pendapatan dari penjualan #' . $transaction->id,
                        'date'           => $transaction->transaction_date ?? now(),
                        'reference_id'   => $transaction->id,
                        'created_by'     => Auth::id() ?? $transaction->created_by,
                    ]);
                }
            }

            return $transaction;
        });
    }
}