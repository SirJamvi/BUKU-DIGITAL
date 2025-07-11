<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\FinancialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        $financialSummary = $this->financialService->getFinancialSummary();
        return view('admin.financial.index', compact('financialSummary'));
    }

    public function cashFlow(): View
    {
        $cashFlows = $this->financialService->getCashFlowWithPagination();
        return view('admin.financial.cash-flow', compact('cashFlows'));
    }

    public function expenses(): View
    {
        $expenses = $this->financialService->getExpensesWithPagination();
        return view('admin.financial.expenses', compact('expenses'));
    }

    public function roiAnalysis(): View
    {
        $roiData = $this->financialService->getRoiAnalysis();
        return view('admin.financial.roi-analysis', compact('roiData'));
    }
}