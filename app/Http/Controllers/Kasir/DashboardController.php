<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Services\Kasir\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menampilkan dashboard utama untuk kasir.
     * [cite_start]Sesuai SOP, dashboard kasir menampilkan ringkasan penjualan harian mereka. [cite: 39, 416]
     *
     * @return View
     */
    public function index(): View
    {
        try {
            $kasirId = Auth::id();
            $data = $this->dashboardService->getDashboardData($kasirId);
            
            return view('kasir.dashboard.index', $data);
        } catch (\Exception $e) {
            logger()->error('Error fetching kasir dashboard data: ' . $e->getMessage());
            return view('kasir.errors.500', ['message' => 'Tidak dapat memuat data dashboard.']);
        }
    }
}