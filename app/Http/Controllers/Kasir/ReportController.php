<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * @var ReportService
     */
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Menampilkan halaman utama laporan untuk kasir.
     *
     * @return View
     */
    public function index(): View
    {
        return view('kasir.reports.index');
    }

    /**
     * Menghasilkan dan menampilkan laporan penjualan untuk kasir yang sedang login.
     * Akses laporan kasir terbatas hanya untuk data penjualan mereka sendiri.
     *
     * @param Request $request
     * @return View
     */
    public function sales(Request $request): View
    {
        try {
            $kasirId = Auth::id();
            $filters = $request->only(['start_date', 'end_date']);
            
            $reportData = $this->reportService->getSalesReport($kasirId, $filters);
            return view('kasir.reports.sales', $reportData);
        } catch (\Exception $e) {
            logger()->error('Error generating kasir sales report: ' . $e->getMessage());
            
            // Kembalikan view dengan error message, bukan redirect
            return view('kasir.reports.sales', [
                'error' => 'Gagal membuat laporan penjualan.',
                'reportData' => []
            ]);
        }
    }
}