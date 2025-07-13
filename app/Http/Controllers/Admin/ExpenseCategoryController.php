<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\Admin\ExpenseCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    protected ExpenseCategoryService $expenseCategoryService;

    public function __construct(ExpenseCategoryService $expenseCategoryService)
    {
        $this->expenseCategoryService = $expenseCategoryService;
    }

    public function index(): View
    {
        $categories = $this->expenseCategoryService->getAllCategoriesWithPagination();
        return view('admin.expense_categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.expense_categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:191', 'type' => 'required|string']);
        $this->expenseCategoryService->createCategory($request->all());
        return redirect()->route('admin.expense_categories.index')->with('success', 'Kategori pengeluaran berhasil dibuat.');
    }
}