<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
        $transactions = $this->transactionService->getTransactionsByKasir(Auth::id());
        return view('kasir.transactions.index', compact('transactions'));
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
}
