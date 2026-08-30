<?php

namespace App\Http\Controllers\Api\V1\Kasir;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\CashFlow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PosApiController extends Controller
{
    /**
     * 1. Mengambil data awal untuk aplikasi POS Android
     */
    public function getPosData(Request $request)
    {
        $businessId = $request->user() ? $request->user()->business_id : 1;

        $products = Product::where('is_active', true)
            ->where('business_id', $businessId)
            ->whereHas('inventory', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            ->with(['category', 'inventory', 'variants'])
            ->get();

        $customers = Customer::where('status', 'active')
            ->orderBy('name', 'asc')
            ->get();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();

        $drivers = User::where('business_id', $businessId)
            ->select('id', 'name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $products,
                'customers' => $customers,
                'paymentMethods' => $paymentMethods,
                'drivers' => $drivers,
            ]
        ]);
    }

    /**
     * 2. Menyimpan order baru dari aplikasi Android (Langsung assign Driver)
     */
    public function storeOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'driver_id' => 'required|exists:users,id',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $transaction = DB::transaction(function () use ($request) {
                $userId = $request->user() ? $request->user()->id : 1;
                $businessId = $request->user() ? $request->user()->business_id : 1;

                foreach ($request->items as $item) {
                    $product = Product::with('inventory')->find($item['product_id']);
                    if ($product->inventory->current_stock < $item['quantity']) {
                        throw new \Exception("Stok untuk produk '{$product->name}' tidak mencukupi.");
                    }
                }

                $transaction = Transaction::create([
                    'business_id' => $businessId,
                    'type' => 'sale',
                    'customer_id' => $request->customer_id,
                    'claimed_by_driver_id' => $request->driver_id,
                    'total_amount' => $request->total_amount,
                    'payment_method' => 'kasbon',
                    'payment_status' => 'pending',
                    'order_status' => 'delivering',
                    'status' => 'completed',
                    'transaction_date' => now(),
                    'notes' => $request->notes,
                    'created_by' => $userId,
                ]);

                foreach ($request->items as $item) {
                    $product = Product::find($item['product_id']);
                    $transaction->details()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);
                    $product->inventory->decrement('current_stock', $item['quantity']);
                }

                return $transaction;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Order berhasil dibuat dan driver telah ditugaskan.',
                'data' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. Menampilkan pesanan yang perlu dikirim (pending) atau sedang dikirim (delivering)
     */
    public function getDeliveryOrders(Request $request)
    {
        $businessId = $request->user() ? $request->user()->business_id : 1;

        $orders = Transaction::with(['customer', 'driver'])
            ->where('business_id', $businessId)
            ->whereIn('order_status', ['pending', 'delivering'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    /**
     * 4. Menugaskan kurir ke beberapa order sekaligus (Bulk Assign)
     */
    public function assignDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'exists:transactions,id',
            'driver_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        Transaction::whereIn('id', $request->transaction_ids)
            ->update([
                'claimed_by_driver_id' => $request->driver_id,
                'order_status' => 'delivering'
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Kurir berhasil ditugaskan. Barang siap diantar!'
        ]);
    }

    /**
     * 5. Konfirmasi pembayaran saat kurir pulang (Bulk Payment)
     */
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'exists:transactions,id',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $transactions = Transaction::whereIn('id', $request->transaction_ids)->get();
            $userId = $request->user() ? $request->user()->id : 1;

            foreach ($transactions as $transaction) {
                $isKasbon = strtolower($request->payment_method) === 'kasbon';

                $transaction->update([
                    'payment_method' => $request->payment_method,
                    'payment_status' => $isKasbon ? 'pending' : 'paid',
                    'order_status' => 'delivered'
                ]);

                if (!$isKasbon) {
                    CashFlow::create([
                        'business_id' => $transaction->business_id,
                        'type' => 'income',
                        'category_id' => 1,
                        'payment_method' => $request->payment_method,
                        'amount' => $transaction->total_amount,
                        'description' => 'Pembayaran pesanan diantar #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                        'date' => now(),
                        'reference_id' => $transaction->id,
                        'created_by' => $userId,
                    ]);

                    if ($transaction->customer_id) {
                        $transaction->customer()->increment('total_purchases', $transaction->total_amount);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($transactions) . ' Transaksi berhasil diselesaikan.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}