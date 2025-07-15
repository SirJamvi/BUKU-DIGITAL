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
     * [BARU] Membuat pengaturan alokasi default hanya untuk bisnis baru.
     * Fungsi ini dipanggil saat registrasi.
     */
    public function createDefaultAllocationSettingsForNewBusiness(int $businessId, int $userId): void
    {
        $defaultSettings = [
            ['allocation_name' => 'Gaji Owner', 'percentage' => 40, 'sort_order' => 1, 'category' => 'Operasional'],
            ['allocation_name' => 'Reinvestasi Bisnis', 'percentage' => 30, 'sort_order' => 2, 'category' => 'Investasi'],
            ['allocation_name' => 'Dana Darurat', 'percentage' => 20, 'sort_order' => 3, 'category' => 'Tabungan'],
            ['allocation_name' => 'Ekspansi & Lainnya', 'percentage' => 10, 'sort_order' => 4, 'category' => 'Lainnya'],
        ];

        foreach ($defaultSettings as $setting) {
            FundAllocationSetting::create([
                'business_id'      => $businessId,
                'allocation_name'  => $setting['allocation_name'],
                'percentage'       => $setting['percentage'],
                'sort_order'       => $setting['sort_order'],
                'category'         => $setting['category'],
                'is_active'        => true,
                'created_by'       => $userId,
            ]);
        }
    }

    /**
     * [DISEMPURNAKAN] Mengambil data alokasi saat ini atau riwayat alokasi terakhir.
     */
    public function getCurrentAllocation(): array
    {
        $businessId      = Auth::user()->business_id;
        $settings        = $this->getAllocationSettings();
        $pendingProfits  = OwnerProfit::where('business_id', $businessId)
                                     ->where('status', 'pending')
                                     ->get();

        if ($pendingProfits->isNotEmpty()) {
            return [
                'view_type'       => 'pending',
                'net_profit'      => $pendingProfits->sum('net_profit'),
                'pending_profits' => $pendingProfits,
                'settings'        => $settings,
                'history'         => collect(),
            ];
        }

        $latestHistory = FundAllocationHistory::where('business_id', $businessId)
                                              ->latest('allocated_at')
                                              ->first();

        if ($latestHistory) {
            $historyForLatestProfit = FundAllocationHistory::where('owner_profit_id', $latestHistory->owner_profit_id)
                                                           ->orderBy('id')
                                                           ->get();
            return [
                'view_type'       => 'summary',
                'net_profit'      => $latestHistory->net_profit ?? 0,
                'pending_profits' => collect(),
                'settings'        => $settings,
                'history'         => $historyForLatestProfit,
            ];
        }

        return [
            'view_type'       => 'empty',
            'net_profit'      => 0,
            'pending_profits' => collect(),
            'settings'        => $settings,
            'history'         => collect(),
        ];
    }

    private function getCurrentNetProfit(): float
    {
        $businessId        = Auth::user()->business_id;
        $latestOwnerProfit = OwnerProfit::where('business_id', $businessId)
                                        ->where('status', 'pending')
                                        ->latest('period_year')
                                        ->latest('period_month')
                                        ->first();

        if ($latestOwnerProfit) {
            return $latestOwnerProfit->net_profit;
        }

        return $this->financialService->getCurrentMonthNetProfit();
    }

    public function getAllocationSettings(): Collection
    {
        return FundAllocationSetting::where('business_id', Auth::user()->business_id)                                  ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get();
    }

    public function updateAllocationSettings(array $data): void
    {
        $totalPercentage = collect($data['settings'])->sum('percentage');
        if ($totalPercentage > 100) {
            throw new \Exception("Total persentase alokasi tidak boleh melebihi 100%.");
        }

        foreach ($data['settings'] as $settingData) {
            $setting = FundAllocationSetting::find($settingData['id']);
            if ($setting) {
                $setting->update(['percentage' => $settingData['percentage']]);
            }
        }
    }

    public function getAllocationHistory(int $perPage = 20): LengthAwarePaginator
    {
        $businessId = Auth::user()->business_id;

        return FundAllocationHistory::where('business_id', $businessId)            ->with('createdBy')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * [FIX FINAL] Memproses alokasi dana otomatis dengan lebih aman
     */
    public function processAllocation(int $ownerProfitId): void
    {
        $businessId = Auth::user()->business_id;
        $ownerProfit = OwnerProfit::where('business_id', $businessId)                                ->where('id', $ownerProfitId)
                                  ->first();

        if (!$ownerProfit || $ownerProfit->status !== 'pending') {
            throw new \Exception('Data owner profit tidak valid atau sudah diproses.');
        }

        $settings = $this->getAllocationSettings();
        if ($settings->isEmpty()) {
            throw new \Exception('Pengaturan alokasi dana belum dibuat.');
        }

        $netProfitToAllocate = $ownerProfit->net_profit;
        if ($netProfitToAllocate <= 0) {
            $ownerProfit->update(['status' => 'completed']);
            return;
        }

        $totalAllocated = 0;

        DB::transaction(function () use ($settings, $ownerProfit, $businessId, $netProfitToAllocate, &$totalAllocated) {
            foreach ($settings as $setting) {
                $allocatedAmount = ($netProfitToAllocate * $setting->percentage) / 100;
                FundAllocationHistory::create([
                    'business_id'         => $businessId,
                    'owner_profit_id'     => $ownerProfit->id,
                    'net_profit'          => $netProfitToAllocate,
                    'allocation_name'     => $setting->allocation_name,
                    'allocation_category' => $setting->category,
                    'allocation_percentage'=> $setting->percentage,
                    'allocated_amount'    => $allocatedAmount,
                    'period_month'        => $ownerProfit->period_month,
                    'period_year'         => $ownerProfit->period_year,
                    'is_manual'           => false,
                    'allocated_at'        => now(),
                    'created_by'          => Auth::id(),
                ]);

                $totalAllocated += $allocatedAmount;
            }

            $ownerProfit->update([
                'allocated_funds' => $totalAllocated,
                'status'          => 'allocated',
                'allocated_at'    => now(),
            ]);
        });
    }

    /**
     * [FIXED] Buat pengaturan alokasi default jika belum ada.
     * Memperbaiki nilai 'category' agar sesuai dengan ENUM di database.
     */
    public function createDefaultAllocationSettings(): void
    {
        $businessId = Auth::user()->business_id;
        $existingCount = FundAllocationSetting::where('business_id', $businessId)->count();

        if ($existingCount > 0) {
            return;
        }

        $defaultSettings = [
            [
                'allocation_name' => 'Gaji Owner',
                'percentage'      => 30,
                'sort_order'      => 1,
                'category'        => 'owner_salary',
            ],
            [
                'allocation_name' => 'Reinvestasi Bisnis',
                'percentage'      => 40,
                'sort_order'      => 2,
                'category'        => 'reinvestment',
            ],
            [
                'allocation_name' => 'Dana Darurat',
                'percentage'      => 20,
                'sort_order'      => 3,
                'category'        => 'emergency',
            ],
            [
                'allocation_name' => 'Dividen Owner',
                'percentage'      => 10,
                'sort_order'      => 4,
                'category'        => 'custom',
            ],
        ];

        DB::transaction(function () use ($businessId, $defaultSettings) {
            foreach ($defaultSettings as $setting) {
                $exists = FundAllocationSetting::where('business_id', $businessId)
                                               ->where('allocation_name', $setting['allocation_name'])
                                               ->exists();

                if (!$exists) {
                    FundAllocationSetting::create([
                        'business_id'      => $businessId,
                        'allocation_name'  => $setting['allocation_name'],
                        'percentage'       => $setting['percentage'],
                        'sort_order'       => $setting['sort_order'],
                        'category'         => $setting['category'],
                        'is_active'        => true,
                        'created_by'       => Auth::id(),
                    ]);
                }
            }
        });
    }

    /**
     * [TAMBAHAN] Method untuk reset atau membuat ulang pengaturan default
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
     * [TAMBAHAN] Method untuk cek apakah pengaturan sudah ada
     */
    public function hasAllocationSettings(): bool
    {
        $businessId = Auth::user()->business_id;

        return FundAllocationSetting::where('business_id', $businessId)
                                    ->where('is_active', true)
                                    ->exists();
    }
}
