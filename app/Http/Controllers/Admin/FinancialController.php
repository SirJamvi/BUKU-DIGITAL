<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialService;
use App\Models\CapitalTracking;
use App\Models\CashFlow;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Exports\ExpensesExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialController extends Controller
{
    use AuthorizesRequests;

    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    // Menampilkan halaman utama Finansial
    public function index(): View
    {
        $financialSummary = $this->financialService->getFinancialSummary();
        return view('admin.financial.index', compact('financialSummary'));
    }

    // Menampilkan halaman Laporan Pengeluaran (Daftar) dengan filter
    public function expenses(Request $request): View
    {
        // Query dasar untuk pengeluaran, pastikan relasi di-load dengan `with()`
        $query = CashFlow::where('type', 'expense')->with('category', 'createdBy');
        
        // Terapkan filter tanggal jika ada input dari user
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        
        // Ambil data dengan paginasi
        $expenses = $query->latest('date')->paginate(10);
        $categories = ExpenseCategory::all();
        
        return view('admin.financial.expenses', compact('expenses', 'categories'));
    }

    // Menampilkan halaman form untuk mencatat pengeluaran baru
    public function createExpense(): View
    {
        // =======================================================
        // PERUBAHAN DI SINI
        // =======================================================
        // 1. Ambil semua kategori pengeluaran yang ada
        $categories = ExpenseCategory::where('business_id', Auth::user()->business_id)
                                     ->orderBy('name')
                                     ->get();
        
        // 2. Kirim data kategori ke view
        return view('admin.financial.create_expense', compact('categories'));
        // =======================================================
    }

    // Menyimpan data pengeluaran baru
    public function storeExpense(Request $request): RedirectResponse
    {
        $request->validate([
            'category_name' => 'required|string|max:191',
            'amount'        => 'required|numeric|min:1',
            'description'   => 'required|string|max:500',
            'date'          => 'required|date',
        ]);
        $this->financialService->createExpense($request->all());
        return redirect()->route('admin.financial.expenses')->with('success', 'Data pengeluaran berhasil disimpan.');
    }

    // Menyimpan kategori pengeluaran baru dari modal
    public function storeExpenseCategory(Request $request): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:191', 'type' => 'required|string']);
        $this->financialService->createExpenseCategory($request->all());
        return back()->with('success', 'Kategori pengeluaran baru berhasil ditambahkan.');
    }

    /**
     * Menangani ekspor data pengeluaran ke Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = CashFlow::where('type', 'expense')->with('category', 'createdBy');
        
        // Terapkan filter yang sama dari request
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        
        // Ambil semua data yang cocok (tanpa paginasi)
        $expenses = $query->latest('date')->get();
        
        $fileName = 'laporan-pengeluaran-' . now()->format('d-m-Y') . '.xlsx';
        return Excel::download(new ExpensesExport($expenses), $fileName);
    }

    /**
     * Menangani ekspor data pengeluaran ke PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = CashFlow::where('type', 'expense')->with('category', 'createdBy');
        
        // Terapkan filter yang sama dari request
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        
        // Ambil semua data yang cocok (tanpa paginasi)
        $expenses = $query->latest('date')->get();
        
        // Load view PDF dengan data
        $pdf = Pdf::loadView('admin.financial.expenses_pdf', compact('expenses'));
        
        $fileName = 'laporan-pengeluaran-' . now()->format('d-m-Y') . '.pdf';
        return $pdf->download($fileName);
    }
    
    public function cashFlow(): View
    {
        $cashFlows = $this->financialService->getCashFlowWithPagination();
        return view('admin.financial.cash-flow', compact('cashFlows'));
    }

    public function roiAnalysis(): View
    {
        $roiData = $this->financialService->getRoiAnalysis();
        return view('admin.financial.roi-analysis', compact('roiData'));
    }

    /**
     * Menampilkan halaman untuk mengatur modal.
     */
    public function capital(): View
    {
        $capital = CapitalTracking::where('business_id', Auth::user()->business_id)->first();
        return view('admin.financial.capital', compact('capital'));
    }

    /**
     * Menyimpan data modal.
     */
    public function storeCapital(Request $request): RedirectResponse
    {
        $request->validate(['initial_capital' => 'required|numeric|min:0']);
        $this->financialService->storeOrUpdateCapital($request->all());
        return redirect()->route('admin.financial.index')->with('success', 'Modal berhasil disimpan.');
    }

    /**
     * Memproses permintaan tutup buku bulanan.
     */
    public function processMonthlyClosing(Request $request): RedirectResponse
    {
        $request->validate(['period' => 'required|date_format:Y-m']);
        
        try {
            $this->financialService->processMonthlyClosing($request->input('period'));
            return redirect()->route('admin.fund-allocation.index')->with('success', 'Proses tutup buku berhasil. Laba bersih siap dialokasikan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses tutup buku: ' . $e->getMessage());
        }
    }

    /**
     * Memproses permintaan tutup buku (alias untuk processMonthlyClosing).
     * Method ini ditambahkan untuk kompatibilitas dengan route yang sudah ada.
     */
    public function processClosing(Request $request): RedirectResponse
    {
        // Validasi input - bisa berupa period (Y-m) atau tanggal lengkap
        $request->validate([
            'period' => 'sometimes|required|date_format:Y-m',
            'closing_date' => 'sometimes|required|date',
        ]);
        
        try {
            // Jika ada parameter period, gunakan processMonthlyClosing
            if ($request->has('period')) {
                $this->financialService->processMonthlyClosing($request->input('period'));
            } 
            // Jika ada closing_date, konversi ke format Y-m
            elseif ($request->has('closing_date')) {
                $closingDate = \Carbon\Carbon::parse($request->input('closing_date'));
                $period = $closingDate->format('Y-m');
                $this->financialService->processMonthlyClosing($period);
            }
            // Jika tidak ada parameter, gunakan bulan saat ini
            else {
                $currentPeriod = now()->format('Y-m');
                $this->financialService->processMonthlyClosing($currentPeriod);
            }
            
            return redirect()->route('admin.fund-allocation.index')
                ->with('success', 'Proses tutup buku berhasil. Laba bersih siap dialokasikan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses tutup buku: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan form untuk mengedit data pengeluaran.
     */
    public function editExpense(CashFlow $expense): View
    {
        $this->authorize('update', $expense);
        return view('admin.financial.edit_expense', compact('expense'));
    }

    /**
     * Memproses pembaruan data pengeluaran.
     */
    public function updateExpense(Request $request, CashFlow $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        
        $request->validate([
            'category_name' => 'required|string|max:191',
            'amount'        => 'required|numeric|min:1',
            'description'   => 'required|string|max:500',
            'date'          => 'required|date',
        ]);

        $this->financialService->updateExpense($expense, $request->all());

        return redirect()->route('admin.financial.expenses')->with('success', 'Data pengeluaran berhasil diperbarui.');
    }

    /**
     * Menghapus data pengeluaran.
     */
    public function destroyExpense(CashFlow $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        
        $this->financialService->deleteExpense($expense);
        
        return redirect()->route('admin.financial.expenses')->with('success', 'Data pengeluaran berhasil dihapus.');
    }

    /**
     * Menampilkan halaman untuk proses tutup buku dengan form.
     */
    public function showClosingForm(): View
    {
        $availablePeriods = $this->financialService->getAvailablePeriodsForClosing();
        return view('admin.financial.closing-form', compact('availablePeriods'));
    }

    /**
     * Mendapatkan data summary untuk periode tertentu (AJAX).
     */
    public function getClosingSummary(Request $request)
    {
        $request->validate(['period' => 'required|date_format:Y-m']);
        
        try {
            $summary = $this->financialService->getClosingSummaryForPeriod($request->input('period'));
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}