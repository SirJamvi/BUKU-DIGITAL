<?php

namespace App\Services\Admin;

use App\Models\FundAllocationSetting;
use App\Models\FundAllocationHistory;
use App\Models\OwnerProfit;
use App\Services\Admin\FinancialService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FundAllocationService
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Membuat pengaturan alokasi default untuk bisnis baru
     */
    public function createDefaultAllocationSettingsForNewBusiness(int $businessId, int $userId): void
    {
        $defaultSettings = [
            [
                'allocation_name' => 'Gaji Owner',
                'category' => 'owner_salary',
                'percentage' => 30,
                'sort_order' => 1
            ],
            [
                'allocation_name' => 'Reinvestasi Bisnis',
                'category' => 'reinvestment',
                'percentage' => 40,
                'sort_order' => 2
            ],
            [
                'allocation_name' => 'Dana Darurat',
                'category' => 'emergency',
                'percentage' => 20,
                'sort_order' => 3
            ],
            [
                'allocation_name' => 'Dividen Owner',
                'category' => 'custom',
                'percentage' => 10,
                'sort_order' => 4
            ],
        ];

        foreach ($defaultSettings as $setting) {
            FundAllocationSetting::create([
                'business_id' => $businessId,
                'allocation_name' => $setting['allocation_name'],
                'category' => $setting['category'],
                'percentage' => $setting['percentage'],
                'sort_order' => $setting['sort_order'],
                'is_active' => true,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * [DISEMPURNAKAN] Mengambil data alokasi saat ini atau riwayat alokasi terakhir.
     */
    public function getCurrentAllocation(): array
    {
        $businessId = Auth::user()->business_id;
        $settings = $this->getAllocationSettings();
        
        // Ambil SEMUA keuntungan yang belum dialokasikan (status pending)
        $pendingProfits = OwnerProfit::where('business_id', $businessId)
                                     ->where('status', 'pending')
                                     ->get();

        // Jika ada profit yang belum dialokasikan, tampilkan form alokasi
        if ($pendingProfits->isNotEmpty()) {
            return [
                'view_type'               => 'pending',
                'net_profit_to_allocate'  => $pendingProfits->sum('net_profit'),
                'pending_profits'         => $pendingProfits, // Kirim ID profit yang akan diproses
                'settings'                => $settings,
            ];
        }

        // Jika tidak ada, tampilkan ringkasan alokasi terakhir
        $latestHistory = FundAllocationHistory::where('business_id', $businessId)
                                              ->latest('allocated_at')
                                              ->first();
        if ($latestHistory) {
            return [
                'view_type'             => 'summary',
                'last_allocated_profit' => $latestHistory->net_profit,
                'history'               => FundAllocationHistory::where('owner_profit_id', $latestHistory->owner_profit_id)->get(),
            ];
        }

        // Tampilan default jika belum ada data sama sekali
        return ['view_type' => 'empty', 'settings' => $settings];
    }
    
    /**
     * [FIX FINAL] Memproses alokasi dana untuk SEMUA profit yang tertunda.
     */
    public function processAllocation(array $ownerProfitIds): void
    {
        $businessId = Auth::user()->business_id;
        
        $profitsToProcess = OwnerProfit::where('business_id', $businessId)
                                      ->whereIn('id', $ownerProfitIds)
                                      ->where('status', 'pending')
                                      ->get();

        if ($profitsToProcess->isEmpty()) {
            throw new \Exception('Tidak ada data profit yang valid untuk diproses.');
        }

        $settings = $this->getAllocationSettings();
        if ($settings->isEmpty()) {
            throw new \Exception('Pengaturan alokasi dana belum dibuat.');
        }
        
        $totalNetProfit = $profitsToProcess->sum('net_profit');

        DB::transaction(function () use ($settings, $profitsToProcess, $businessId, $totalNetProfit) {
            // Hanya buat history jika ada profit positif untuk dialokasikan
            if ($totalNetProfit > 0) {
                foreach ($settings as $setting) {
                    $allocatedAmount = ($totalNetProfit * $setting->percentage) / 100;
                    
                    FundAllocationHistory::create([
                        'business_id'         => $businessId,
                        'owner_profit_id'     => $profitsToProcess->first()->id, // Pakai ID pertama sebagai referensi
                        'net_profit'          => $totalNetProfit,
                        'allocation_name'     => $setting->allocation_name,
                        'allocation_category' => $setting->category,
                        'allocation_percentage'=> $setting->percentage,
                        'allocated_amount'    => $allocatedAmount,
                        'is_manual'           => false,
                        'allocated_at'        => now(),
                        'created_by'          => Auth::id(),
                    ]);
                }
            }

            // Tandai SEMUA profit yang diproses sebagai 'completed'
            OwnerProfit::whereIn('id', $profitsToProcess->pluck('id'))->update([
                'status' => 'completed',
                'allocated_at' => now(),
            ]);
        });
    }

    /**
     * Proses alokasi manual untuk profit tunggal
     */
    public function processManualAllocation(int $ownerProfitId, array $allocations): void
    {
        $businessId = Auth::user()->business_id;
        $ownerProfit = OwnerProfit::where('business_id', $businessId)
                                  ->where('id', $ownerProfitId)
                                  ->where('status', 'pending')
                                  ->first();

        if (!$ownerProfit) {
            throw new \Exception('Data profit tidak valid atau sudah diproses.');
        }

        $totalAllocated = collect($allocations)->sum('amount');
        if ($totalAllocated > $ownerProfit->net_profit) {
            throw new \Exception('Total alokasi melebihi profit yang tersedia.');
        }

        DB::transaction(function () use ($allocations, $ownerProfit, $businessId, $totalAllocated) {
            foreach ($allocations as $allocation) {
                if ($allocation['amount'] > 0) {
                    FundAllocationHistory::create([
                        'business_id' => $businessId,
                        'owner_profit_id' => $ownerProfit->id,
                        'net_profit' => $ownerProfit->net_profit,
                        'allocation_name' => $allocation['name'],
                        'allocation_category' => $allocation['category'] ?? 'custom',
                        'allocation_percentage' => ($allocation['amount'] / $ownerProfit->net_profit) * 100,
                        'allocated_amount' => $allocation['amount'],
                        'period_month' => $ownerProfit->period_month,
                        'period_year' => $ownerProfit->period_year,
                        'is_manual' => true,
                        'allocated_at' => now(),
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            $ownerProfit->update([
                'status' => 'allocated',
                'allocated_at' => now(),
                'allocated_funds' => $totalAllocated,
            ]);
        });
    }

    /**
     * Ambil pengaturan alokasi aktif
     */
    public function getAllocationSettings(): Collection
    {
        return FundAllocationSetting::where('business_id', Auth::user()->business_id)
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get();
    }

    /**
     * Update pengaturan alokasi
     */
    public function updateAllocationSettings(array $data): void
    {
        $totalPercentage = collect($data['settings'])->sum('percentage');
        if ($totalPercentage > 100) {
            throw new \Exception("Total persentase alokasi tidak boleh melebihi 100%.");
        }

        DB::transaction(function () use ($data) {
            foreach ($data['settings'] as $settingData) {
                $setting = FundAllocationSetting::find($settingData['id']);
                if ($setting && $setting->business_id == Auth::user()->business_id) {
                    $setting->update([
                        'percentage' => $settingData['percentage'],
                        'allocation_name' => $settingData['allocation_name'] ?? $setting->allocation_name,
                    ]);
                }
            }
        });
    }

    /**
     * Tambah pengaturan alokasi baru
     */
    public function addAllocationSetting(array $data): void
    {
        $businessId = Auth::user()->business_id;
        
        // Cek total persentase existing
        $currentTotal = FundAllocationSetting::where('business_id', $businessId)
                                             ->where('is_active', true)
                                             ->sum('percentage');

        if ($currentTotal + $data['percentage'] > 100) {
            throw new \Exception("Total persentase alokasi tidak boleh melebihi 100%.");
        }

        // Ambil sort_order terakhir
        $lastOrder = FundAllocationSetting::where('business_id', $businessId)
                                          ->max('sort_order') ?? 0;

        FundAllocationSetting::create([
            'business_id' => $businessId,
            'allocation_name' => $data['allocation_name'],
            'category' => $data['category'] ?? 'custom',
            'percentage' => $data['percentage'],
            'sort_order' => $lastOrder + 1,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Hapus pengaturan alokasi
     */
    public function deleteAllocationSetting(int $settingId): void
    {
        $setting = FundAllocationSetting::where('id', $settingId)
                                        ->where('business_id', Auth::user()->business_id)
                                        ->first();

        if (!$setting) {
            throw new \Exception('Pengaturan alokasi tidak ditemukan.');
        }

        $setting->delete();
    }

    /**
     * Ambil riwayat alokasi dengan pagination
     */
    public function getAllocationHistory(int $perPage = 20): LengthAwarePaginator
    {
        $businessId = Auth::user()->business_id;

        return FundAllocationHistory::where('business_id', $businessId)
                                    ->with('createdBy')
                                    ->latest('allocated_at')
                                    ->paginate($perPage);
    }

    /**
     * Ambil riwayat alokasi berdasarkan periode
     */
    public function getAllocationHistoryByPeriod(int $month, int $year): Collection
    {
        $businessId = Auth::user()->business_id;

        return FundAllocationHistory::where('business_id', $businessId)
                                    ->where('period_month', $month)
                                    ->where('period_year', $year)
                                    ->orderBy('id')
                                    ->get();
    }

    /**
     * Buat pengaturan alokasi default jika belum ada
     */
    public function createDefaultAllocationSettings(): void
    {
        $businessId = Auth::user()->business_id;
        $existingCount = FundAllocationSetting::where('business_id', $businessId)->count();

        if ($existingCount > 0) {
            return;
        }

        $this->createDefaultAllocationSettingsForNewBusiness($businessId, Auth::id());
    }

    /**
     * Reset pengaturan ke default
     */
    public function resetToDefaultSettings(): void
    {
        $businessId = Auth::user()->business_id;

        DB::transaction(function () use ($businessId) {
            FundAllocationSetting::where('business_id', $businessId)->delete();
            $this->createDefaultAllocationSettings();
        });
    }

    /**
     * Cek apakah pengaturan sudah ada
     */
    public function hasAllocationSettings(): bool
    {
        $businessId = Auth::user()->business_id;

        return FundAllocationSetting::where('business_id', $businessId)
                                    ->where('is_active', true)
                                    ->exists();
    }

    /**
     * Ambil ringkasan alokasi
     */
    public function getAllocationSummary(): array
    {
        $businessId = Auth::user()->business_id;
        
        // Total profit yang telah dialokasikan
        $totalAllocated = OwnerProfit::where('business_id', $businessId)
                                     ->where('status', 'allocated')
                                     ->sum('allocated_funds');

        // Total profit yang belum dialokasikan
        $totalPending = OwnerProfit::where('business_id', $businessId)
                                   ->where('status', 'pending')
                                   ->sum('net_profit');

        // Riwayat alokasi terakhir
        $latestAllocations = FundAllocationHistory::where('business_id', $businessId)
                                                  ->latest('allocated_at')
                                                  ->limit(5)
                                                  ->get();

        // Breakdown alokasi berdasarkan kategori
        $allocationBreakdown = FundAllocationHistory::where('business_id', $businessId)
                                                    ->selectRaw('allocation_category, SUM(allocated_amount) as total_amount')
                                                    ->groupBy('allocation_category')
                                                    ->get();

        return [
            'total_allocated' => $totalAllocated,
            'total_pending' => $totalPending,
            'latest_allocations' => $latestAllocations,
            'allocation_breakdown' => $allocationBreakdown,
        ];
    }

    /**
     * Validasi data alokasi
     */
    public function validateAllocationData(array $data): array
    {
        $errors = [];

        if (empty($data['allocation_name'])) {
            $errors[] = 'Nama alokasi tidak boleh kosong.';
        }

        if (!isset($data['percentage']) || $data['percentage'] <= 0) {
            $errors[] = 'Persentase alokasi harus lebih dari 0.';
        }

        if (isset($data['percentage']) && $data['percentage'] > 100) {
            $errors[] = 'Persentase alokasi tidak boleh melebihi 100%.';
        }

        return $errors;
    }

    /**
     * Ambil data untuk dashboard alokasi
     */
    public function getDashboardData(): array
    {
        $businessId = Auth::user()->business_id;
        
        $currentAllocation = $this->getCurrentAllocation();
        $allocationSummary = $this->getAllocationSummary();
        $settings = $this->getAllocationSettings();

        return [
            'current_allocation' => $currentAllocation,
            'allocation_summary' => $allocationSummary,
            'settings' => $settings,
            'has_pending_profits' => $currentAllocation['view_type'] === 'pending',
            'total_settings' => $settings->count(),
            'settings_percentage_total' => $settings->sum('percentage'),
        ];
    }
}