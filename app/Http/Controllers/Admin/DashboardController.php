<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon; // Pastikan Carbon di-import

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
    public function index(Request $request): View
    {
        try {
            // Mengambil semua data dashboard dari service bawaan
            $dashboardData = $this->dashboardService->getDashboardData();

            // --- SETUP FILTER TANGGAL ---
            // Mengambil input tanggal dari URL (jika ada), default-nya adalah hari ini
            $dateInput = $request->input('filter_date');
            $filterDate = $dateInput ? Carbon::parse($dateInput)->startOfDay() : Carbon::today();

            // Simpan ke array untuk dikirim ke view
            $dashboardData['filterDate'] = $filterDate->format('Y-m-d');
            $dashboardData['isToday'] = $filterDate->isToday();
            $dashboardData['formattedDate'] = $filterDate->isoFormat('D MMMM Y');

            // Mengambil stok realtime (tidak terpengaruh filter tanggal, selalu stok saat ini)
            $dashboardData['realtimeStocks'] = \App\Models\Inventory::with('product')->get();

            // Mengambil data pergerakan stok (miss opname) berdasarkan tanggal filter
            $dashboardData['missedOpnames'] = \App\Models\StockMovement::with(['product', 'createdBy'])
                ->whereDate('created_at', $filterDate)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->where(function ($query) {
                    $query->where('type', 'adjustment')
                        ->orWhere('type', 'opname');
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // --- HISTORY PECAH BALL BERDASARKAN TANGGAL FILTER ---

            // 1. Ambil pergerakan stok KELUAR (Karung/Ball yang dipecah)
            $breakUnitOuts = \App\Models\StockMovement::with(['product', 'createdBy'])
                ->whereDate('created_at', $filterDate)
                ->where('notes', 'Pecah Ball (Bahan Baku)')
                ->orderBy('created_at', 'desc')
                ->get();

            // 2. Ambil pergerakan stok MASUK (Hasil pecahan eceran)
            $breakUnitIns = \App\Models\StockMovement::with(['product'])
                ->whereDate('created_at', $filterDate)
                ->where('notes', 'LIKE', 'Hasil Pecahan dari Produk ID:%')
                ->orderBy('created_at', 'desc')
                ->get();

            // 3. Pasangkan data Induk (yang dipecah) dengan Anaknya (hasil pecahannya)
            $breakUnitHistory = [];
            foreach ($breakUnitOuts as $out) {
                // Kita kelompokkan berdasarkan menit yang sama dan ID produk asal
                $timeKey = $out->created_at->format('Y-m-d H:i');

                $hasil = $breakUnitIns->filter(function ($in) use ($timeKey, $out) {
                    return $in->created_at->format('Y-m-d H:i') === $timeKey &&
                        str_contains($in->notes, 'Produk ID: ' . $out->product_id);
                });

                $breakUnitHistory[] = [
                    'time' => $out->created_at,
                    'kasir' => $out->createdBy->name ?? 'Kasir',
                    'source_product' => $out->product->name ?? 'Produk Dihapus',
                    'source_qty' => $out->quantity,
                    'targets' => $hasil // Kumpulan kemasan hasil
                ];
            }

            $dashboardData['breakUnitHistory'] = $breakUnitHistory;

            return view('admin.dashboard.index', $dashboardData);
        } catch (\Exception $e) {
            logger()->error('Error fetching dashboard data: ' . $e->getMessage());
            return view('admin.dashboard.index', [
                'error' => 'Tidak dapat memuat data dashboard. Silakan coba lagi nanti.'
            ]);
        }
    }
}
