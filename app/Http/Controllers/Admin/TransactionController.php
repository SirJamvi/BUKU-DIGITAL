<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Admin\TransactionService as AdminTransactionService;
use App\Services\Kasir\TransactionService as KasirTransactionService;
use App\Http\Requests\Kasir\StoreTransactionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionController extends Controller
{
    use AuthorizesRequests;

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

    public function edit(Transaction $transaction): View
    {
        $data = app(KasirTransactionService::class)->getEditTransactionData($transaction);
        return view('admin.transactions.edit', $data);
    }

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

    /**
     * [BARU] Memproses penghapusan transaksi beserta rollback stok dan kas.
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        try {
            // Ambil ID dan tanggal untuk pesan sukses sebelum data hilang
            $txId = $transaction->id;

            $this->transactionService->deleteTransaction($transaction);

            return redirect()
                ->route('admin.transactions.index')
                ->with('success', "Transaksi #{$txId} berhasil dihapus. Stok, Poin, dan Arus Kas telah dikembalikan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}
