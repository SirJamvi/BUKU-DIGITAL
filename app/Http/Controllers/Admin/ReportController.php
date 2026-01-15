<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use App\Services\Admin\FinancialService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    protected ReportService $reportService;
    protected FinancialService $financialService;

    public function __construct(ReportService $reportService, FinancialService $financialService)
    {
        $this->reportService = $reportService;
        $this->financialService = $financialService;
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
        $reportData = $this->financialService->getFinancialReport($request->all());
        return view('admin.reports.financial', compact('reportData'));
    }

    public function inventory(Request $request): View
    {
        $reportData = $this->reportService->getInventoryReport($request->all());
        return view('admin.reports.inventory', compact('reportData'));
    }

    /**
     * Menangani ekspor Laporan Keuangan ke PDF.
     */
    public function exportFinancialPdf(Request $request)
    {
        // Gunakan FinancialService untuk mendapatkan data yang sudah difilter
        $reportData = $this->financialService->getFinancialReport($request->all());

        // Load view khusus untuk PDF dengan data yang didapat
        $pdf = Pdf::loadView('admin.reports.financial_pdf', compact('reportData'));

        // Beri nama file dan kirim sebagai unduhan
        $fileName = 'laporan-keuangan-' . now()->format('d-m-Y') . '.pdf';
        return $pdf->download($fileName);
    }
}