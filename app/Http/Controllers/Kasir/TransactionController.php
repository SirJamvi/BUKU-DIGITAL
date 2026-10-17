<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use App\Models\CashFlow;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

class TransactionController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var TransactionService
     */
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Menampilkan daftar transaksi.
     */
    public function index(): View
    {
        $kasirId = Auth::id();
        $businessId = Auth::user()->business_id;
        $transactions = $this->transactionService->getTransactionsByKasir($kasirId);

        // Ringkasan Transaksi Hari Ini Kasir (WIB / Asia/Jakarta)
        $today = Carbon::today('Asia/Jakarta');
        $todayStart = Carbon::today('Asia/Jakarta')->startOfDay();
        $todayEnd = Carbon::today('Asia/Jakarta')->endOfDay();

        $todayTransactionsQuery = Transaction::where('business_id', $businessId)
            ->where('created_by', $kasirId)
            ->where('type', 'sale')
            ->where(function ($q) use ($today, $todayStart, $todayEnd) {
                $q->whereBetween('transaction_date', [$todayStart, $todayEnd])
                  ->orWhereDate('transaction_date', $today->toDateString());
            });

        $todaySales = (float) $todayTransactionsQuery->sum('total_amount');
        $todayTransactionsCount = $todayTransactionsQuery->count();

        return view('kasir.transactions.index', compact('transactions', 'todaySales', 'todayTransactionsCount'));
    }

    /**
     * Menampilkan detail transaksi (struk).
     */
    public function show(Transaction $transaction): View
    {
        // Keamanan sudah ditangani oleh Global Scope
        $transactionDetails = $this->transactionService->getTransactionDetails($transaction);
        return view('kasir.pos.receipt', $transactionDetails);
    }

    /**
     * [BARU] Memproses pelunasan transaksi yang sebelumnya kasbon.
     */
    public function markAsPaid(\Illuminate\Http\Request $request, Transaction $transaction): RedirectResponse
    {
        // ==========================================================
        // PERBAIKAN #403: Ganti Policy authorize() dengan cek manual
        // Policy 'update' untuk Transaction belum diatur untuk role Kasir,
        // sehingga selalu ditolak (403). Kita cek langsung business_id-nya.
        // ==========================================================
        if ($transaction->business_id !== Auth::user()->business_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        $request->validate([
            'payment_method' => 'required|string'
        ]);

        try {
            // 1. Update status transaksi
            $transaction->update([
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method
            ]);

            // 2. Catat ke CashFlow (Karena uang baru benar-benar masuk sekarang)
            \App\Models\CashFlow::create([
                'business_id' => $transaction->business_id,
                'type' => 'income', // Sesuaikan dengan enum di sistem Anda
                'category_id' => 1,
                'payment_method' => $request->payment_method,
                'amount' => $transaction->total_amount,
                'description' => 'Pelunasan Transaksi Kasbon #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                'date' => now(),
                'reference_id' => $transaction->id,
                'created_by' => Auth::id(),
            ]);

            return back()->with('success', 'Transaksi berhasil divalidasi dan dicatat sebagai Lunas.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memvalidasi pelunasan: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Menampilkan form untuk mengedit transaksi.
     */
    public function edit(Transaction $transaction): View
    {
        // Perbaikan #2: Otorisasi menggunakan Policy
        $this->authorize('update', $transaction);

        $data = $this->transactionService->getEditTransactionData($transaction);
        return view('kasir.transactions.edit', $data);
    }

    /**
     * [BARU] Memproses pembaruan transaksi.
     */
    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        // Perbaikan #2: Otorisasi menggunakan Policy
        $this->authorize('update', $transaction);

        try {
            $this->transactionService->updateTransaction($transaction, $request->validated());
            return redirect()
                ->route('kasir.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui transaksi: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * [BARU] Memproses pelunasan banyak transaksi (Bulk Payment) sekaligus.
     */
    public function bulkMarkAsPaid(\Illuminate\Http\Request $request): RedirectResponse
    {
        $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer|exists:transactions,id',
            'payment_method' => 'required|string'
        ], [
            'transaction_ids.required' => 'Anda belum memilih transaksi satupun untuk dilunasi.'
        ]);

        $businessId = Auth::user()->business_id;
        $transactionIds = $request->transaction_ids;

        // Pastikan transaksi yang diubah benar-benar milik toko ini dan statusnya masih pending
        $transactions = Transaction::whereIn('id', $transactionIds)
            ->where('business_id', $businessId)
            ->where('payment_status', 'pending')
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Tidak ada transaksi valid yang dapat diproses.');
        }

        try {
            DB::beginTransaction();

            foreach ($transactions as $transaction) {
                // 1. Update status masing-masing transaksi
                $transaction->update([
                    'payment_status' => 'paid',
                    'payment_method' => $request->payment_method
                ]);

                // 2. Catat ke CashFlow untuk setiap transaksi agar laporan tercatat rapi
                \App\Models\CashFlow::create([
                    'business_id' => $transaction->business_id,
                    'type' => 'income',
                    'category_id' => 1,
                    'payment_method' => $request->payment_method,
                    'amount' => $transaction->total_amount,
                    'description' => 'Pelunasan Kasbon (Bulk) #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                    'date' => now(),
                    'reference_id' => $transaction->id,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return back()->with('success', count($transactions) . ' Transaksi berhasil dilunasi secara bersamaan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pelunasan massal: ' . $e->getMessage());
        }
    }

    /**
     * [BARU] Memproses penyelesaian pengiriman driver kolektif dengan real payment per transaksi.
     */
    public function completeDeliveryTrip(\Illuminate\Http\Request $request): RedirectResponse
    {
        $request->validate([
            'trip_code' => 'required|string',
            'payments' => 'required|array',
            'notes' => 'nullable|array',
        ]);

        $businessId = Auth::user()->business_id;
        $tripCode = $request->trip_code;

        $transactions = Transaction::with('customer')
            ->where('business_id', $businessId)
            ->where('delivery_trip_code', $tripCode)
            ->get();

        if ($transactions->isEmpty()) {
            return back()->with('error', 'Tidak ditemukan transaksi valid untuk nomor pengiriman ini.');
        }

        try {
            DB::beginTransaction();

            $paidCount = 0;
            $kasbonCount = 0;

            foreach ($transactions as $transaction) {
                $chosenMethod = $request->payments[$transaction->id] ?? 'kasbon';
                $orderNote = $request->notes[$transaction->id] ?? null;
                $isKasbon = strtolower($chosenMethod) === 'kasbon';

                $methodSlug = $isKasbon ? 'kasbon' : \Illuminate\Support\Str::slug($chosenMethod);

                $updateData = [
                    'payment_method' => $methodSlug,
                    'payment_status' => $isKasbon ? 'pending' : 'paid',
                    'order_status'   => 'delivered',
                    'delivery_status'=> 'delivered',
                    'delivered_at'   => now(),
                ];

                if (!empty($orderNote)) {
                    $updateData['notes'] = ($transaction->notes ? $transaction->notes . ' | ' : '') . $orderNote;
                }

                $transaction->update($updateData);

                if (!$isKasbon) {
                    $paidCount++;

                    // Catat ke CashFlow secara dinamis dengan metode pembayaran riil
                    \App\Models\CashFlow::create([
                        'business_id'    => $transaction->business_id,
                        'type'           => 'income',
                        'category_id'    => 1,
                        'payment_method' => $methodSlug,
                        'amount'         => $transaction->total_amount,
                        'description'    => 'Pelunasan Pengiriman ' . $tripCode . ' #' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT) . ' (' . ($transaction->customer->name ?? 'Pelanggan Umum') . ')',
                        'date'           => now(),
                        'reference_id'   => $transaction->id,
                        'created_by'     => Auth::id(),
                    ]);

                    // Tambahkan akumulasi belanja customer
                    if ($transaction->customer_id) {
                        $customer = \App\Models\Customer::find($transaction->customer_id);
                        if ($customer) {
                            $customer->increment('total_purchases', $transaction->total_amount);
                        }
                    }
                } else {
                    $kasbonCount++;
                }
            }

            DB::commit();

            $msg = "Pengiriman #{$tripCode} berhasil diselesaikan! ";
            if ($paidCount > 0) {
                $msg .= "{$paidCount} transaksi lunas dicatat ke kasir. ";
            }
            if ($kasbonCount > 0) {
                $msg .= "{$kasbonCount} transaksi tetap dicatat sebagai Kasbon/Piutang.";
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('completeDeliveryTrip error: ' . $e->getMessage());
            return back()->with('error', 'Gagal menyelesaikan pengiriman driver: ' . $e->getMessage());
        }
    }
}
