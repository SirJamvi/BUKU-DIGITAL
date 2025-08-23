<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Kasir\StoreTransactionRequest; // <-- Perbaikan #1: Menambahkan use statement
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- PERBAIKAN: Import trait

class TransactionController extends Controller
{
    use AuthorizesRequests; // <-- PERBAIKAN: Menggunakan trait untuk authorize method

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