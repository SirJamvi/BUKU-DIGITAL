<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        return view('admin.reports.index');
    }

    public function sales(Request $request): View
    {
        $reportData = $this->reportService->getSalesReport($request->all());
        return view('admin.reports.sales', compact('reportData'));
    }

    public function financial(Request $request): View
    {
        $reportData = $this->reportService->getFinancialReport($request->all());
        return view('admin.reports.financial', compact('reportData'));
    }

    public function inventory(Request $request): View
    {
        $reportData = $this->reportService->getInventoryReport($request->all());
        return view('admin.reports.inventory', compact('reportData'));
    }
}
