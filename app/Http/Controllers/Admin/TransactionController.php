<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\TransactionService as KasirTransactionService; // <-- Perbaikan #1
use App\Http\Requests\Kasir\StoreTransactionRequest; // <-- Perbaikan #2
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- Perbaikan #3
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use AuthorizesRequests; // <-- Perbaikan #4

    protected AdminTransactionService $transactionService;

    public function __construct(AdminTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(): View
    {
        $transactions = $this->transactionService->getAllTransactionsWithPagination();
        return view('admin.transactions.index', compact('transactions'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load('customer', 'createdBy', 'details.product');
        return view('admin.transactions.show', compact('transaction'));
    }

    /**
     * [BARU] Menampilkan form untuk admin mengedit transaksi.
     */
    public function edit(Transaction $transaction): View
    {
        // Policy sudah otomatis memberikan izin kepada admin
        $data = app(KasirTransactionService::class)->getEditTransactionData($transaction);
        
        return view('admin.transactions.edit', $data);
    }

    /**
     * [BARU] Memproses pembaruan transaksi oleh admin.
     */
    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        try {
            app(KasirTransactionService::class)->updateTransaction($transaction, $request->validated());
            return redirect()
                ->route('admin.transactions.show', $transaction->id)
                ->with('success', 'Transaksi berhasil diperbarui oleh Admin.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui transaksi: ' . $e->getMessage())->withInput();
        }
    }
}