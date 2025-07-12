<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Admin\TransactionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
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
}