<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Menampilkan halaman dashboard admin.
     */
    public function index(): View
    {
        try {
            // Mengambil semua data dashboard dari service bawaan kamu
            $dashboardData = $this->dashboardService->getDashboardData();

            // --- TAMBAHAN UNTUK MONITORING OPNAME HARI INI ---
            $today = \Carbon\Carbon::today();

            // Mengambil stok realtime
            $dashboardData['realtimeStocks'] = \App\Models\Inventory::with('product')->get();

            // Mengambil data pergerakan stok (miss opname) hari ini. 
            // Asumsi: saat ada selisih, sistem mencatat 'type' sebagai 'adjustment' atau tipe sejenis di StockMovement.
            $dashboardData['missedOpnames'] = \App\Models\StockMovement::with(['product', 'createdBy'])
                ->whereDate('created_at', $today) // <-- UBAH DISINI: Gunakan created_at
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->where(function ($query) {
                    // Cari yang tipenya adjustment/opname (sesuaikan dengan nama tipe di sistemmu)
                    $query->where('type', 'adjustment')
                        ->orWhere('type', 'opname');
                })
                ->orderBy('created_at', 'desc') // <-- UBAH DISINI: Gunakan created_at
                ->get();


            return view('admin.dashboard.index', $dashboardData);
        } catch (\Exception $e) {
            logger()->error('Error fetching dashboard data: ' . $e->getMessage());
            return view('admin.dashboard.index', [
                'error' => 'Tidak dapat memuat data dashboard. Silakan coba lagi nanti.'
            ]);
        }
    }
}
