<?php

namespace App\Services\Kasir;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosService
{
    /**
     * Mengambil data yang diperlukan untuk antarmuka POS.
     */
    public function getPosData(): array
    {
        $businessId = Auth::user()->business_id;

        $products = Product::where('is_active', true)
            ->whereHas('inventory', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            // Tambahkan 'variants' di dalam array with()
            ->with(['category', 'inventory', 'variants'])
            ->get();

        $customers = Customer::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();

        // [BARU] Ambil Metode Pembayaran Dinamis
        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        // [BARU] Ambil Driver Dinamis (Role Driver milik toko yang aktif)
        $drivers = \App\Models\User::where('business_id', $businessId)
            ->where('role', 'driver')
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        if ($drivers->isEmpty()) {
            $drivers = \App\Models\User::where('business_id', $businessId)
                ->where('role', 'driver')
                ->orderBy('name', 'asc')
                ->get();
        }

        return [
            'products' => $products,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'drivers' => $drivers,
        ];
    }

    /**
     * Generate Kode Batch / Ritase Pengiriman (Contoh: TRIP-261013-01)
     */
    public function generateTripCode(int $businessId): string
    {
        // Pastikan kolom delivery_trip_code tersedia
        if (!\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'delivery_trip_code')) {
            try {
                \Illuminate\Support\Facades\Schema::table('transactions', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('delivery_trip_code', 50)->nullable()->after('order_status');
                });
            } catch (\Exception $e) {
                // Abaikan jika sudah ada
            }
        }

        $todayPrefix = 'TRIP-' . date('ymd') . '-';
        $lastTrip = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', today())
            ->whereNotNull('delivery_trip_code')
            ->orderByDesc('id')
            ->value('delivery_trip_code');

        if ($lastTrip && str_starts_with($lastTrip, $todayPrefix)) {
            $num = (int) substr($lastTrip, strlen($todayPrefix));
            $nextSeq = str_pad($num + 1, 2, '0', STR_PAD_LEFT);
        } else {
            $nextSeq = '01';
        }

        return $todayPrefix . $nextSeq;
    }

    /**
     * Memproses Pengiriman Rombongan (Multi-Customer Delivery).
     */
    public function processBatchDelivery(array $data): array
    {
        $businessId = Auth::user()->business_id;

        // 1. Akumulasi total qty yang dibutuhkan per produk untuk semua pesanan
        $totalRequiredPerProduct = [];
        foreach ($data['orders'] as $order) {
            foreach ($order['items'] as $item) {
                $pid = $item['product_id'];
                $totalRequiredPerProduct[$pid] = ($totalRequiredPerProduct[$pid] ?? 0) + $item['quantity'];
            }
        }

        // 2. Validasi stok untuk seluruh pesanan sekaligus
        foreach ($totalRequiredPerProduct as $pid => $requiredQty) {
            $product = Product::with('inventory')->find($pid);
            if (!$product || $product->inventory->current_stock < $requiredQty) {
                $pName = $product ? $product->name : 'Produk ID ' . $pid;
                $currentStock = $product ? $product->inventory->current_stock : 0;
                throw new InsufficientStockException(
                    "Stok tidak mencukupi untuk total pesanan rombongan pada produk '{$pName}'. Butuh: {$requiredQty}, Tersedia: {$currentStock}."
                );
            }
        }

        $tripCode = $this->generateTripCode($businessId);

        return DB::transaction(function () use ($data, $businessId, $tripCode) {
            $createdTransactions = [];
            $driverId = $data['driver_id'] ?? null;

            foreach ($data['orders'] as $orderIndex => $order) {
                $orderTotal = $order['total_amount'] ?? 0;

                $transaction = Transaction::create([
                    'business_id' => $businessId,
                    'type' => 'sale',
                    'customer_id' => $order['customer_id'] ?? null,
                    'driver_id' => $driverId,
                    'claimed_by_driver_id' => $driverId,
                    'delivery_trip_code' => $tripCode,
                    'total_amount' => $orderTotal,
                    'payment_method' => 'kasbon',
                    'payment_status' => 'pending',
                    'order_status' => 'delivering',
                    'delivery_status' => 'delivering',
                    'status' => 'completed',
                    'transaction_date' => now(),
                    'notes' => $order['notes'] ?? ("Pengiriman {$tripCode}"),
                    'created_by' => Auth::id(),
                ]);

                foreach ($order['items'] as $item) {
                    $product = Product::find($item['product_id']);

                    $transaction->details()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);

                    $product->inventory->decrement('current_stock', $item['quantity']);
                }

                $createdTransactions[] = $transaction;
            }

            return [
                'is_batch' => true,
                'trip_code' => $tripCode,
                'transactions' => $createdTransactions,
            ];
        });
    }

    /**
     * Memproses dan menyimpan transaksi baru dari POS.
     * Mendukung single order maupun multi-order batch delivery.
     */
    public function processTransaction(array $data)
    {
        // Jika terdapat banyak pesanan (Multi-Order Delivery)
        if (isset($data['orders']) && is_array($data['orders'])) {
            return $this->processBatchDelivery($data);
        }

        return DB::transaction(function () use ($data) {
            // 1. Validasi stok sebelum membuat transaksi (TETAP SAMA)
            foreach ($data['items'] as $item) {
                $product = Product::with('inventory')->find($item['product_id']);
                if ($product->inventory->current_stock < $item['quantity']) {
                    throw new InsufficientStockException(
                        "Stok untuk produk '{$product->name}' tidak mencukupi."
                    );
                }
            }

            // Cek apakah ini transaksi kasbon
            $isKasbon = $data['payment_method'] === 'kasbon';
            $driverId = $data['driver_id'] ?? null;

            // 2. Buat transaksi utama
            $transaction = Transaction::create([
                'business_id' => Auth::user()->business_id,
                'type' => 'sale',
                'customer_id' => $data['customer_id'] ?? null,
                'driver_id' => $driverId,
                'claimed_by_driver_id' => $driverId,
                'total_amount' => $data['total_amount'],
                // PERBAIKAN: Masukkan string 'kasbon' alih-alih null
                'payment_method' => $isKasbon ? 'kasbon' : $data['payment_method'],
                'payment_status' => $isKasbon ? 'pending' : 'paid',
                'order_status' => $driverId ? 'delivering' : null,
                'delivery_status' => $driverId ? 'delivering' : 'pending',
                'status' => 'completed',
                'transaction_date' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // 3. Simpan detail transaksi dan kurangi stok (TETAP SAMA)
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);

                $transaction->details()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);

                $product->inventory->decrement('current_stock', $item['quantity']);
            }

            // 4. Catat ke Cash Flow HANYA JIKA LUNAS
            if (!$isKasbon) {
                CashFlow::create([
                    'business_id' => $transaction->business_id,
                    'type' => 'income',
                    'category_id' => 1,
                    'amount' => $transaction->total_amount,
                    'payment_method' => $transaction->payment_method,
                    'description' => 'Pendapatan dari penjualan #' . $transaction->id,
                    'date' => $transaction->transaction_date,
                    'reference_id' => $transaction->id,
                    'created_by' => Auth::id(),
                ]);
            }

            // Update total belanja pelanggan (TETAP SAMA)
            if ($transaction->customer_id) {
                $customer = Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->increment('total_purchases', $transaction->total_amount);
                }
            }

            return $transaction;
        });
    }

    /**
     * Get transaction with all related data for receipt.
     */
    public function getTransactionWithDetails(int $transactionId): Transaction
    {
        return Transaction::with([
            'details.product',
            'customer',
            'createdBy'
        ])->findOrFail($transactionId);
    }
}
