<?php

namespace App\Services\Kasir;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\PaymentMethod;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PosService
{
    /**
     * Mengambil data yang diperlukan untuk antarmuka POS.
     */
    public function getPosData(): array
    {
        $businessId = Auth::user()->business_id;
        $branchId = app(BranchContext::class)->getBranchId();

        $productQuery = Product::where('is_active', true);
        if ($branchId) {
            $productQuery->whereHas('inventory', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->where('current_stock', '>', 0);
            })->with([
                'category',
                'inventory' => fn($q) => $q->where('branch_id', $branchId),
                'variants'
            ]);
        } else {
            $productQuery->whereHas('inventory', function ($query) {
                $query->where('current_stock', '>', 0);
            })->with(['category', 'inventory', 'variants']);
        }
        $products = $productQuery->get();

        $customers = Customer::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();

        // Ambil Metode Pembayaran Dinamis
        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        // Ambil Driver Dinamis (utamakan driver di cabang aktif jika ada)
        $driverQuery = User::where('business_id', $businessId)
            ->where('role', 'driver')
            ->where('is_active', true);

        if ($branchId) {
            $branchDrivers = (clone $driverQuery)->where(function ($q) use ($branchId) {
                $q->whereHas('branches', fn($b) => $b->where('branches.id', $branchId))
                  ->orWhere('branch_id', $branchId);
            })->orderBy('name', 'asc')->get();

            $drivers = $branchDrivers->isNotEmpty() ? $branchDrivers : $driverQuery->orderBy('name', 'asc')->get();
        } else {
            $drivers = $driverQuery->orderBy('name', 'asc')->get();
        }

        return [
            'products' => $products,
            'customers' => $customers,
            'paymentMethods' => $paymentMethods,
            'drivers' => $drivers,
        ];
    }

    /**
     * Generate Kode Batch / Ritase Pengiriman (Format: TRIP-{branch.code}-yymmdd-XX)
     */
    public function generateTripCode(?int $businessId = null, ?int $branchId = null): string
    {
        $businessId = $businessId ?? Auth::user()->business_id;
        $branchId = $branchId ?? app(BranchContext::class)->getBranchId();

        $branchCode = 'CB';
        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch && !empty($branch->code)) {
                $branchCode = $branch->code;
            }
        }

        $todayPrefix = 'TRIP-' . $branchCode . '-' . date('ymd') . '-';
        $tripQuery = Transaction::where('business_id', $businessId)
            ->whereDate('transaction_date', today())
            ->whereNotNull('delivery_trip_code');

        if ($branchId) {
            $tripQuery->where('branch_id', $branchId);
        }

        $lastTrip = $tripQuery->orderByDesc('id')->value('delivery_trip_code');

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
        $branchId = app(BranchContext::class)->requireBranchId();

        // 1. Akumulasi total qty yang dibutuhkan per produk untuk semua pesanan
        $totalRequiredPerProduct = [];
        foreach ($data['orders'] as $order) {
            foreach ($order['items'] as $item) {
                $pid = $item['product_id'];
                $totalRequiredPerProduct[$pid] = ($totalRequiredPerProduct[$pid] ?? 0) + $item['quantity'];
            }
        }

        // 2. Validasi stok untuk seluruh pesanan sekaligus di cabang aktif
        foreach ($totalRequiredPerProduct as $pid => $requiredQty) {
            $inventory = Inventory::where('product_id', $pid)
                ->where('branch_id', $branchId)
                ->first();

            $currentStock = $inventory ? (float) $inventory->current_stock : 0;
            if ($currentStock < $requiredQty) {
                $product = Product::find($pid);
                $pName = $product ? $product->name : 'Produk ID ' . $pid;
                throw new InsufficientStockException(
                    "Stok tidak mencukupi untuk total pesanan rombongan pada produk '{$pName}'. Butuh: {$requiredQty}, Tersedia di cabang ini: {$currentStock}."
                );
            }
        }

        $tripCode = $this->generateTripCode($businessId, $branchId);

        return DB::transaction(function () use ($data, $businessId, $branchId, $tripCode) {
            $createdTransactions = [];
            $driverId = $data['driver_id'] ?? null;

            foreach ($data['orders'] as $orderIndex => $order) {
                $orderTotal = $order['total_amount'] ?? 0;

                $transaction = Transaction::create([
                    'business_id'          => $businessId,
                    'branch_id'            => $branchId,
                    'type'                 => 'sale',
                    'customer_id'          => $order['customer_id'] ?? null,
                    'driver_id'            => $driverId,
                    'claimed_by_driver_id' => $driverId,
                    'delivery_trip_code'   => $tripCode,
                    'total_amount'         => $orderTotal,
                    'payment_method'       => 'kasbon',
                    'payment_status'       => 'pending',
                    'order_status'         => 'delivering',
                    'delivery_status'      => 'delivering',
                    'status'               => 'completed',
                    'transaction_date'     => now(),
                    'notes'                => $order['notes'] ?? ("Pengiriman {$tripCode}"),
                    'created_by'           => Auth::id(),
                ]);

                foreach ($order['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    $inventory = Inventory::firstOrCreate(
                        ['product_id' => $item['product_id'], 'branch_id' => $branchId, 'business_id' => $businessId],
                        ['current_stock' => 0, 'cost_price' => (float) ($product->cost_price ?? 0)]
                    );
                    $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();

                    if ($inventory->current_stock < $item['quantity']) {
                        throw new InsufficientStockException("Stok untuk produk '{$product->name}' tidak mencukupi di cabang ini.");
                    }

                    // Tentukan snapshot cost_price (Prioritas: inventory.cost_price -> products.cost_price -> 7 days avg)
                    $snapshotCost = 0.0;
                    if ($product && !$product->isService()) {
                        $branchHpp = (float) ($inventory->cost_price ?? 0);
                        if ($branchHpp > 0) {
                            $snapshotCost = $branchHpp;
                        } else {
                            $masterCost = (float) ($product->cost_price ?? 0);
                            if ($masterCost > 0) {
                                $snapshotCost = $masterCost;
                            } else {
                                $avg7DaysQuery = DB::table('transaction_details')
                                    ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
                                    ->where('transactions.business_id', $businessId)
                                    ->where('transaction_details.product_id', $product->id)
                                    ->where('transaction_details.cost_price', '>', 0)
                                    ->where('transactions.transaction_date', '>=', now()->subDays(7));

                                app(BranchContext::class)->applyTo($avg7DaysQuery, 'transactions', 'branch_id');
                                $avg7Days = $avg7DaysQuery->avg('transaction_details.cost_price');

                                if ($avg7Days !== null && (float) $avg7Days > 0) {
                                    $snapshotCost = round((float) $avg7Days, 2);
                                } else {
                                    $snapshotCost = $masterCost;
                                    Log::warning("POS Snapshot Fallback: Produk {$product->name} (ID: {$product->id}, Business: {$businessId}, Branch: {$branchId}) HPP <= 0 dan tidak ada riwayat snapshot positif 7 hari terakhir.");
                                }
                            }
                        }
                    }

                    $transaction->details()->create([
                        'product_id'  => $item['product_id'],
                        'quantity'    => $item['quantity'],
                        'cost_price'  => $snapshotCost,
                        'unit_price'  => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);

                    $inventory->decrement('current_stock', $item['quantity']);

                    // Catat mutasi stok keluar bertipe 'sale' per cabang
                    StockMovement::create([
                        'business_id'    => $businessId,
                        'branch_id'      => $branchId,
                        'product_id'     => $item['product_id'],
                        'type'           => 'sale',
                        'quantity'       => $item['quantity'],
                        'unit_cost'      => $snapshotCost,
                        'reference_type' => Transaction::class,
                        'reference_id'   => (string) $transaction->id,
                        'notes'          => "Penjualan POS #" . $transaction->id . " [{$tripCode}]",
                        'created_by'     => Auth::id(),
                    ]);
                }

                $createdTransactions[] = $transaction;
            }

            \App\Support\BranchCache::flush($businessId);

            return [
                'is_batch'     => true,
                'trip_code'    => $tripCode,
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
            $branchId = app(BranchContext::class)->requireBranchId();
            $businessId = (int) Auth::user()->business_id;

            // 1. Validasi stok sebelum membuat transaksi di cabang aktif
            foreach ($data['items'] as $item) {
                $inventory = Inventory::where('product_id', $item['product_id'])
                    ->where('branch_id', $branchId)
                    ->first();

                $currentStock = $inventory ? (float) $inventory->current_stock : 0;
                if ($currentStock < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    $pName = $product ? $product->name : 'Produk ID ' . $item['product_id'];
                    throw new InsufficientStockException(
                        "Stok untuk produk '{$pName}' tidak mencukupi di cabang ini."
                    );
                }
            }

            // Cek apakah ada driver yang ditugaskan (Pengiriman)
            $driverId = !empty($data['driver_id']) ? (int) $data['driver_id'] : null;

            // Berdasarkan kesepakatan: Jika ada driver, otomatis Kasbon / Pending sampai driver kembali
            $isDelivery = !empty($driverId);
            $isKasbon = $isDelivery || (($data['payment_method'] ?? '') === 'kasbon');

            // Generate trip_code jika pengiriman driver (baik 1 pesanan maupun rombongan)
            $tripCode = $isDelivery ? $this->generateTripCode($businessId, $branchId) : null;

            // 2. Buat transaksi utama
            $transaction = Transaction::create([
                'business_id'          => $businessId,
                'branch_id'            => $branchId,
                'type'                 => 'sale',
                'customer_id'          => $data['customer_id'] ?? null,
                'driver_id'            => $driverId,
                'claimed_by_driver_id' => $driverId,
                'delivery_trip_code'   => $tripCode,
                'total_amount'         => $data['total_amount'],
                'payment_method'       => $isKasbon ? 'kasbon' : $data['payment_method'],
                'payment_status'       => $isKasbon ? 'pending' : 'paid',
                'order_status'         => $driverId ? 'delivering' : null,
                'delivery_status'      => $driverId ? 'delivering' : 'pending',
                'status'               => 'completed',
                'transaction_date'     => now(),
                'notes'                => $data['notes'] ?? null,
                'created_by'           => Auth::id(),
            ]);

            // 3. Simpan detail transaksi dan kurangi stok
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $item['product_id'], 'branch_id' => $branchId, 'business_id' => $businessId],
                    ['current_stock' => 0, 'cost_price' => (float) ($product->cost_price ?? 0)]
                );
                $inventory = Inventory::where('id', $inventory->id)->lockForUpdate()->first();

                if ($inventory->current_stock < $item['quantity']) {
                    throw new InsufficientStockException("Stok untuk produk '{$product->name}' tidak mencukupi di cabang ini.");
                }

                // Tentukan snapshot cost_price (Prioritas: inventory.cost_price -> products.cost_price -> 7 days avg)
                $snapshotCost = 0.0;
                if ($product && !$product->isService()) {
                    $branchHpp = (float) ($inventory->cost_price ?? 0);
                    if ($branchHpp > 0) {
                        $snapshotCost = $branchHpp;
                    } else {
                        $masterCost = (float) ($product->cost_price ?? 0);
                        if ($masterCost > 0) {
                            $snapshotCost = $masterCost;
                        } else {
                            $avg7DaysQuery = DB::table('transaction_details')
                                ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
                                ->where('transactions.business_id', $businessId)
                                ->where('transaction_details.product_id', $product->id)
                                ->where('transaction_details.cost_price', '>', 0)
                                ->where('transactions.transaction_date', '>=', now()->subDays(7));

                            app(BranchContext::class)->applyTo($avg7DaysQuery, 'transactions', 'branch_id');
                            $avg7Days = $avg7DaysQuery->avg('transaction_details.cost_price');

                            if ($avg7Days !== null && (float) $avg7Days > 0) {
                                $snapshotCost = round((float) $avg7Days, 2);
                            } else {
                                $snapshotCost = $masterCost;
                                Log::warning("POS Snapshot Fallback: Produk {$product->name} (ID: {$product->id}, Business: {$businessId}, Branch: {$branchId}) HPP <= 0 dan tidak ada riwayat snapshot positif 7 hari terakhir.");
                            }
                        }
                    }
                }

                $transaction->details()->create([
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'cost_price'  => $snapshotCost,
                    'unit_price'  => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);

                $inventory->decrement('current_stock', $item['quantity']);

                // Catat mutasi stok keluar bertipe 'sale' per cabang
                StockMovement::create([
                    'business_id'    => $businessId,
                    'branch_id'      => $branchId,
                    'product_id'     => $item['product_id'],
                    'type'           => 'sale',
                    'quantity'       => $item['quantity'],
                    'unit_cost'      => $snapshotCost,
                    'reference_type' => Transaction::class,
                    'reference_id'   => (string) $transaction->id,
                    'notes'          => "Penjualan POS #" . $transaction->id,
                    'created_by'     => Auth::id(),
                ]);
            }

            // 4. Catat ke Cash Flow HANYA JIKA LUNAS
            if (!$isKasbon) {
                CashFlow::create([
                    'business_id'    => $transaction->business_id,
                    'branch_id'      => $branchId,
                    'type'           => 'income',
                    'category_id'    => 1,
                    'amount'         => $transaction->total_amount,
                    'payment_method' => $transaction->payment_method,
                    'description'    => 'Pendapatan dari penjualan #' . $transaction->id,
                    'date'           => $transaction->transaction_date,
                    'reference_id'   => $transaction->id,
                    'created_by'     => Auth::id(),
                ]);
            }

            // Update total belanja pelanggan
            if ($transaction->customer_id) {
                $customer = Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->increment('total_purchases', $transaction->total_amount);
                }
            }

            \App\Support\BranchCache::flush($businessId);

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
            'createdBy',
            'driver',
            'branch'
        ])->findOrFail($transactionId);
    }
}
