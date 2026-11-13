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
        
        // Tambahan untuk dropdown tahun dinamis
        $currentYear = now()->year;
        $startYear = 2020; // Tahun awal bisnis atau bisa disesuaikan
        $years = range($currentYear + 1, $startYear); // Dari tahun depan sampai tahun awal
        
        // Daftar bulan
        $months = [
            '01' => 'Januari',
            '02' => 'Februari', 
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember'
        ];
        
        return view('admin.financial.index', compact('financialSummary', 'years', 'months'));
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
        // 1. Ambil kategori pengeluaran
        $categories = ExpenseCategory::where('business_id', Auth::user()->business_id)
                                     ->orderBy('name')
                                     ->get();
        
        // 2. [BARU] Ambil metode pembayaran dari Service (Database)
        $paymentMethods = $this->financialService->getPaymentMethods();
        
        // 3. Kirim kedua data tersebut ke View
        return view('admin.financial.create_expense', compact('categories', 'paymentMethods'));
    }

    // Menyimpan data pengeluaran baru
    public function storeExpense(Request $request): RedirectResponse
    {
        $request->validate([
            'category_name'  => 'required|string|max:191',
            'amount'         => 'required|numeric|min:1',
            'description'    => 'required|string|max:500',
            'date'           => 'required|date',
            
            // [BARU] Validasi Payment Method: Wajib diisi & harus ada di kolom 'slug' tabel payment_methods
            'payment_method' => 'required|exists:payment_methods,slug',
            'receipt_image'  => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
        ]);
        
        $payload = $request->all();
        if ($request->hasFile('receipt_image')) {
            $payload['receipt_image'] = \App\Services\ProofUploadService::uploadReceipt($request->file('receipt_image'));
        }

        $this->financialService->createExpense($payload);
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
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $totalExpense = (float) $expenses->sum('amount');
        
        // Load view PDF dengan data
        $pdf = Pdf::loadView('admin.financial.expenses_pdf', compact('expenses', 'startDate', 'endDate', 'totalExpense'));
        
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
        // Validasi untuk menerima bulan dan tahun terpisah atau period langsung
        $request->validate([
            'period' => 'required_without_all:month,year|date_format:Y-m',
            'month' => 'required_without:period|string|size:2',
            'year' => 'required_without:period|integer|min:2020|max:' . (now()->year + 1),
        ]);
        
        try {
            // Jika ada month dan year terpisah, gabungkan menjadi period
            if ($request->has('month') && $request->has('year')) {
                $period = $request->input('year') . '-' . $request->input('month');
            } else {
                $period = $request->input('period');
            }

            $date = \Carbon\Carbon::createFromFormat('Y-m', $period);
            $month = $date->month;
            $year = $date->year;
            $businessId = Auth::user()->business_id;
            $branchContext = app(\App\Support\BranchContext::class);

            if (!$branchContext->isConsolidated()) {
                $branchId = $branchContext->requireBranchId();
                $this->financialService->computeBranchClosing($businessId, $branchId, $month, $year, Auth::id(), null, false);
                return redirect()->route('admin.financial.branch-closings')->with('success', 'Tutup buku cabang berhasil diproses.');
            }
            
            $this->financialService->processMonthlyClosing($period, $businessId, Auth::id());
            return redirect()->route('admin.fund-allocation.index')->with('success', 'Proses tutup buku konsolidasi berhasil. Laba bersih siap dialokasikan.');
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
        // Validasi input - bisa berupa period (Y-m), month+year terpisah, atau tanggal lengkap
        $request->validate([
            'period' => 'sometimes|required|date_format:Y-m',
            'month' => 'sometimes|required|string|size:2',
            'year' => 'sometimes|required|integer|min:2020|max:' . (now()->year + 1),
            'closing_date' => 'sometimes|required|date',
        ]);
        
        try {
            // Jika ada parameter month dan year terpisah
            if ($request->has('month') && $request->has('year')) {
                $period = $request->input('year') . '-' . $request->input('month');
            } elseif ($request->has('period')) {
                $period = $request->input('period');
            } elseif ($request->has('closing_date')) {
                $closingDate = \Carbon\Carbon::parse($request->input('closing_date'));
                $period = $closingDate->format('Y-m');
            } else {
                $period = now()->format('Y-m');
            }

            $date = \Carbon\Carbon::createFromFormat('Y-m', $period);
            $month = $date->month;
            $year = $date->year;
            $businessId = Auth::user()->business_id;
            $branchContext = app(\App\Support\BranchContext::class);
            $mode = $branchContext->getMode();

            \Illuminate\Support\Facades\Log::info("ProcessClosing initiated", [
                'mode'        => $mode,
                'period'      => $period,
                'business_id' => $businessId,
                'user_id'     => Auth::id(),
                'branch_id'   => $branchContext->getBranchId(),
            ]);

            if (!$branchContext->isConsolidated()) {
                $branchId = $branchContext->requireBranchId();
                $result = $this->financialService->computeBranchClosing($businessId, $branchId, $month, $year, Auth::id(), null, false);
                \Illuminate\Support\Facades\Log::info("ProcessClosing completed (Branch)", [
                    'branch_id' => $branchId,
                    'result'    => is_array($result) ? $result : ['id' => $result->id ?? null, 'net_profit' => $result->net_profit ?? null],
                ]);
                return redirect()->route('admin.financial.branch-closings')->with('success', 'Tutup buku cabang berhasil diproses.');
            }

            $result = $this->financialService->processMonthlyClosing($period, $businessId, Auth::id());
            \Illuminate\Support\Facades\Log::info("ProcessClosing completed (Consolidated)", [
                'result' => is_array($result) ? $result : ['id' => $result->id ?? null, 'net_profit' => $result->net_profit ?? null],
            ]);

            return redirect()->route('admin.fund-allocation.index')->with('success', 'Tutup buku konsolidasi berhasil diproses.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("ProcessClosing failed: " . $e->getMessage(), [
                'period' => $period ?? null,
                'error'  => $e->getMessage(),
            ]);
            return back()->with('error', 'Gagal memproses tutup buku: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan riwayat rekonsiliasi tutup buku bulanan per cabang.
     */
    public function branchClosings(Request $request): View
    {
        $businessId = Auth::user()->business_id;
        $context = app(\App\Support\BranchContext::class);

        $query = \App\Models\BranchMonthlyClosing::where('business_id', $businessId)
            ->with(['branch', 'closedBy']);

        if ($context->isBranch() && $context->getBranchId() !== null) {
            $query->where('branch_id', $context->getBranchId());
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('year')) {
            $query->where('period_year', $request->year);
        }
        if ($request->filled('month')) {
            $query->where('period_month', $request->month);
        }

        $closings = $query->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderBy('branch_id')
            ->paginate(15);

        $branches = \App\Models\Branch::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $availablePeriods = $this->financialService->getAvailablePeriodsForClosing();

        return view('admin.financial.branch_closings', compact('closings', 'branches', 'availablePeriods'));
    }

    /**
     * Menampilkan form untuk mengedit data pengeluaran.
     */
    public function editExpense(CashFlow $expense): View
    {
        $this->authorize('update', $expense);
        
        // Ambil semua kategori pengeluaran untuk dropdown
        $categories = ExpenseCategory::where('business_id', Auth::user()->business_id)
                                     ->orderBy('name')
                                     ->get();
        
        // [BARU] Ambil metode pembayaran dari Service (Database)
        $paymentMethods = $this->financialService->getPaymentMethods();
        
        return view('admin.financial.edit_expense', compact('expense', 'categories', 'paymentMethods'));
    }

    /**
     * Memproses pembaruan data pengeluaran.
     */
    public function updateExpense(Request $request, CashFlow $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        
        $request->validate([
            'category_name'  => 'required|string|max:191',
            'amount'         => 'required|numeric|min:1',
            'description'    => 'required|string|max:500',
            'date'           => 'required|date',
            
            // [BARU] Validasi Payment Method: Wajib diisi & harus ada di kolom 'slug' tabel payment_methods
            'payment_method' => 'required|exists:payment_methods,slug',
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