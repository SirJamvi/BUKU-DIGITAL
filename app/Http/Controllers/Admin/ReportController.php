<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

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

    /**
     * Menangani ekspor Laporan Keuangan ke PDF.
     */
    public function exportFinancialPdf(Request $request)
    {
        // 1. Gunakan service yang sama untuk mendapatkan data yang sudah difilter
        $reportData = $this->reportService->getFinancialReport($request->all());

        // 2. Load view khusus untuk PDF dengan data yang didapat
        $pdf = Pdf::loadView('admin.reports.financial_pdf', compact('reportData'));

        // 3. Beri nama file dan kirim sebagai unduhan
        $fileName = 'laporan-keuangan-' . now()->format('d-m-Y') . '.pdf';
        return $pdf->download($fileName);
    }
}