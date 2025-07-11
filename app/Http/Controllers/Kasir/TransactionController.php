<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Kasir\TransactionService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class TransactionController extends Controller
{
    /**
     * @var TransactionService
     */
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * [cite_start]Menampilkan daftar transaksi yang dibuat oleh kasir yang sedang login. [cite: 75]
     *
     * @return View
     */
    public function index(): View
    {
        $kasirId = Auth::id();
        $transactions = $this->transactionService->getTransactionsByKasir($kasirId);
        return view('kasir.transactions.index', compact('transactions'));
    }

    /**
     * Menampilkan detail dari satu transaksi.
     * Memastikan kasir hanya bisa melihat transaksi miliknya.
     *
     * @param Transaction $transaction
     * @return View
     */
    public function show(Transaction $transaction): View
    {
        // Otorisasi: pastikan kasir hanya bisa melihat transaksinya sendiri
        if (Auth::id() !== $transaction->created_by) {
            throw new AuthorizationException('Anda tidak berwenang melihat transaksi ini.');
        }

        $transactionDetails = $this->transactionService->getTransactionDetails($transaction);
        return view('kasir.transactions.show', $transactionDetails);
    }
}