<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    public function create(): View
    {
        $expenseCategories = $this->financialService->getExpenseCategories();
        return view('admin.financial.create_expense', compact('expenseCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        // INI BAGIAN YANG DIPERBARUI
        $request->validate([
            'category_name' => 'required|string|max:191', // Validasi input teks
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
        ]);
        $this->financialService->createExpense($request->all());
        return redirect()->route('admin.financial.index')->with('success', 'Data pengeluaran berhasil disimpan.');
    }
}