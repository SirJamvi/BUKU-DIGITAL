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
            'funding_source' => 'nullable|string|in:drawer,external',
            'receipt_image'  => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ]);
        
        $categoryName = $request->input('category_name');
        $user = Auth::user();

        if ($categoryName === '__NEW__') {
            $request->validate([
                'new_category_name' => 'required|string|max:191',
                'new_category_type' => 'nullable|in:HPP,Operasional,Piutang Karyawan,Non-usaha',
            ]);

            $rawCategoryName = trim($request->input('new_category_name'));

            // 1. Cek case-insensitive apakah kategori sudah ada untuk mencegah duplikasi nama seperti 'Bensin' vs 'bensin'
            $existing = ExpenseCategory::where('business_id', $user->business_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($rawCategoryName)])
                ->first();

            if ($existing) {
                // Re-use nama kategori yang sudah ada
                $categoryName = $existing->name;
            } else {
                // Buat kategori baru: Kasir default Operasional (is_cogs = false) agar margin HPP terjaga, admin tetap bisa pilih
                $isUserAdmin = (method_exists($user, 'isAdmin') && $user->isAdmin()) || in_array($user->role ?? '', ['admin', 'owner']);
                $type = ($isUserAdmin && $request->filled('new_category_type'))
                    ? $request->input('new_category_type')
                    : 'Operasional';

                $isCogs = ($type === 'HPP');

                $categoryPayload = [
                    'business_id' => $user->business_id,
                    'name'        => $rawCategoryName,
                    'type'        => $type,
                    'is_active'   => true,
                    'created_by'  => $user->id,
                    'is_cogs'     => $isCogs,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('expense_categories', 'category_type')) {
                    $categoryPayload['category_type'] = $type;
                }

                $newCategory = ExpenseCategory::create($categoryPayload);
                $categoryName = $newCategory->name;
            }
        }

        $payload = $request->all();
        $payload['category_name'] = $categoryName;

        if ($request->hasFile('receipt_image')) {
            $payload['receipt_image'] = \App\Services\ProofUploadService::uploadReceipt($request->file('receipt_image'));
        }

        $this->financialService->createExpense($payload);

        return redirect()->route('kasir.expenses.index')->with('success', 'Data pengeluaran berhasil dicatat.');
    }
}