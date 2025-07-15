<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinancialController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    public function index(): View
    {
        $financialSummary = $this->financialService->getFinancialSummary();
        return view('admin.financial.index', compact('financialSummary'));
    }

    public function expenses(): View
    {
        $expenses   = $this->financialService->getExpensesWithPagination();
        $categories = $this->financialService->getExpenseCategories();
        return view('admin.financial.expenses', compact('expenses', 'categories'));
    }

    public function createExpense(): View
    {
        return view('admin.financial.create_expense');
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $request->validate([
            'category_name' => 'required|string|max:191',
            'amount'        => 'required|numeric|min:1',
            'description'   => 'required|string|max:500',
            'date'          => 'required|date',
        ]);

        $this->financialService->createExpense($request->all());

        return redirect()->route('admin.financial.expenses')
            ->with('success', 'Data pengeluaran berhasil disimpan.');
    }

    public function storeExpenseCategory(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string',
        ]);

        $this->financialService->createExpenseCategory($request->all());

        return back()->with('success', 'Kategori pengeluaran baru berhasil ditambahkan.');
    }

    public function cashFlow(): View
    {
        $cashFlows = $this->financialService->getCashFlowWithPagination();
        return view('admin.financial.cash-flow', compact('cashFlows'));
    }

    public function roiAnalysis(): View
    {
        $roiData = $this->financialService->getRoiAnalysis();

        $debugInfo = [
            'has_capital_data' => $roiData['has_capital_data'] ?? false,
            'data_source'      => $roiData['data_source'] ?? 'unknown',
            'warning_message'  => $roiData['warning_message'] ?? null,
        ];

        return view('admin.financial.roi-analysis', compact('roiData', 'debugInfo'));
    }

    public function initializeFinancialData(Request $request): RedirectResponse
    {
        $request->validate([
            'initial_capital' => 'required|numeric|min:1000000',
        ]);

        try {
            $result = $this->financialService->initializeBusinessFinancialData(
                $request->initial_capital
            );

            return redirect()->route('admin.financial.roi-analysis')
                ->with('success', $result['message']);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menginisialisasi data: ' . $e->getMessage());
        }
    }

    public function processMonthlyClosing(Request $request): RedirectResponse
    {
        // Validasi input, pastikan formatnya YYYY-MM
        $request->validate([
            'period' => 'required|date_format:Y-m',
        ]);
        try {
            // Panggil service untuk memproses tutup buku
            $this->financialService->processMonthlyClosing($request->period);
            // Redirect kembali ke halaman ROI dengan pesan sukses
            return redirect()->route('admin.financial.roi-analysis')
                ->with('success', 'Data profit bulanan berhasil disinkronkan dan diperbarui!');
        } catch (\Exception $e) {
            // Jika terjadi error, kembali dengan pesan kesalahan
            return redirect()->back()
                ->with('error', 'Gagal melakukan sinkronisasi: ' . $e->getMessage());
        }
    }
}