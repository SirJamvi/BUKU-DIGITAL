<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialService;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    // Menampilkan daftar pengeluaran untuk Kasir
    public function index(Request $request): View
    {
        // Query dasar: Ambil pengeluaran bisnis ini
        $query = CashFlow::where('type', 'expense')
            ->where('business_id', Auth::user()->business_id)
            ->with('category', 'createdBy');
        
        // Filter tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        
        $expenses = $query->latest('date')->paginate(10);
        $categories = ExpenseCategory::where('business_id', Auth::user()->business_id)->get();
        
        return view('kasir.expenses.index', compact('expenses', 'categories'));
    }

    // Form catat pengeluaran baru
    public function create(): View
    {
        $categories = ExpenseCategory::where('business_id', Auth::user()->business_id)
            ->orderBy('name')
            ->get();
        
        $paymentMethods = $this->financialService->getPaymentMethods();
        
        return view('kasir.expenses.create', compact('categories', 'paymentMethods'));
    }

    // Proses simpan pengeluaran
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'category_name'  => 'required|string|max:191',
            'amount'         => 'required|numeric|min:1',
            'description'    => 'required|string|max:500',
            'date'           => 'required|date',
            'payment_method' => 'required|exists:payment_methods,slug',
        ]);
        
        $this->financialService->createExpense($request->all());
        
        return redirect()->route('kasir.expenses.index')->with('success', 'Data pengeluaran berhasil dicatat.');
    }
}