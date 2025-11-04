<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FundAllocationRequest;
use App\Services\Admin\FundAllocationService;
use App\Services\Admin\FinancialService;
use App\Models\FundAllocationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth; // <-- 1. TAMBAHKAN IMPORT INI

class FundAllocationController extends Controller
{
    protected FundAllocationService $fundAllocationService;
    protected FinancialService $financialService;

    public function __construct(
        FundAllocationService $fundAllocationService,
        FinancialService $financialService
    ) {
        $this->fundAllocationService = $fundAllocationService;
        $this->financialService = $financialService;
    }

    /**
     * Menampilkan dashboard alokasi dana
     */
    public function index(): View
    {
        $allocationData = $this->fundAllocationService->getCurrentAllocation();
        $financialSummary = $this->financialService->getFinancialSummary();
        
        return view('admin.fund-allocation.index', [
            'allocationData' => $allocationData,
            'financialSummary' => $financialSummary
        ]);
    }

    /**
     * Menampilkan halaman pengaturan alokasi
     */
    public function settings(): View
    {
        $this->fundAllocationService->createDefaultAllocationSettings();
        $settings = $this->fundAllocationService->getAllocationSettings();
        return view('admin.fund-allocation.settings', compact('settings'));
    }

    /**
     * Memperbarui pengaturan alokasi
     */
    public function updateSettings(FundAllocationRequest $request): RedirectResponse
    {
        try {
            $this->fundAllocationService->updateAllocationSettings($request->validated());
            return redirect()
                ->route('admin.fund-allocation.settings')
                ->with('success', 'Pengaturan alokasi dana berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui pengaturan: ' . $e->getMessage());
        }
    }
    
    /**
     * Memproses alokasi dana dari form
     */
    public function processAllocation(Request $request): RedirectResponse
    {
        $request->validate(['owner_profit_ids' => 'required|array']);
        
        try {
            $this->fundAllocationService->processAllocation($request->input('owner_profit_ids'));
            return redirect()
                ->route('admin.fund-allocation.index')
                ->with('success', 'Alokasi dana berhasil diproses.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses alokasi: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan riwayat alokasi
     */
    public function history(): View
    {
        $history = $this->fundAllocationService->getAllocationHistory();
        return view('admin.fund-allocation.history', compact('history'));
    }

    /**
     * Menambah kategori alokasi baru (AJAX)
     */
    public function storeSetting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'allocation_name' => 'required|string|max:100',
            'percentage' => 'required|numeric|min:0|max:100',
            'category' => 'required|string|max:191',
        ]);

        try {
            $this->fundAllocationService->addAllocationSetting($validated);
            return response()->json([
                'success' => true, 
                'message' => 'Kategori alokasi berhasil ditambahkan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Menghapus kategori alokasi (AJAX)
     * * ✅ PERBAIKAN: Menggunakan Facade Auth
     */
    public function destroySetting(FundAllocationSetting $setting): JsonResponse
    {
        // Ambil user yang sedang login
        // =======================================================
        // 2. PERBAIKI BARIS INI
        // =======================================================
        $user = Auth::user(); 
        
        // Validasi: pastikan user sudah login
        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Unauthorized - User tidak terautentikasi.'
            ], 401);
        }

        // Validasi: pastikan user memiliki business_id
        if (!isset($user->business_id) || !$user->business_id) {
            return response()->json([
                'success' => false, 
                'message' => 'Business ID tidak ditemukan untuk user ini.'
            ], 403);
        }

        // Otorisasi: pastikan setting milik bisnis yang sedang login
        if ($setting->business_id !== $user->business_id) {
            return response()->json([
                'success' => false, 
                'message' => 'Tidak diizinkan mengakses data bisnis lain.'
            ], 403);
        }
        
        try {
            $this->fundAllocationService->deleteAllocationSetting($setting->id);
            return response()->json([
                'success' => true, 
                'message' => 'Kategori alokasi berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 500);
        }
    }
}