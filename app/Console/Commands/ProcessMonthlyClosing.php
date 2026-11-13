<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Admin\FinancialService;
use App\Services\Admin\FundAllocationService;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProcessMonthlyClosing extends Command
{
    /**
     * Signature command: mendukung --period dan --dry-run
     */
    protected $signature = 'app:process-monthly-closing {--period= : Periode yang akan diproses (format: YYYY-MM)} {--dry-run : Menjalankan simulasi perhitungan tanpa menyimpan ke database}';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Menjalankan proses tutup buku per cabang, beban holding, dan alokasi dana bulanan secara otomatis untuk semua bisnis.';

    protected FinancialService $financialService;
    protected FundAllocationService $fundAllocationService;

    /**
     * Buat instance command baru.
     */
    public function __construct(FinancialService $financialService, FundAllocationService $fundAllocationService)
    {
        parent::__construct();
        $this->financialService = $financialService;
        $this->fundAllocationService = $fundAllocationService;
    }

    /**
     * Jalankan console command.
     */
    public function handle()
    {
        $isDryRun = (bool) $this->option('dry-run');
        $logPrefix = $isDryRun ? '[AutoClosing-DryRun]' : '[AutoClosing]';

        Log::info("{$logPrefix} Memulai proses tutup buku...");
        
        $periodInput = $this->option('period');
        if ($periodInput) {
            $period = $periodInput;
            $this->warn("Menjalankan proses untuk periode: {$period}" . ($isDryRun ? ' [DRY-RUN]' : ''));
        } else {
            $period = Carbon::now()->subMonth()->format('Y-m');
            $this->info("Menjalankan proses untuk periode: {$period}" . ($isDryRun ? ' [DRY-RUN]' : ''));
        }

        return app(\App\Support\BranchContext::class)->runUnscoped(function () use ($period, $isDryRun, $logPrefix) {
            $businesses = Business::all();

            foreach ($businesses as $business) {
                $this->info("\n=======================================================");
                $this->info("Bisnis: {$business->name} (ID: {$business->id}) | Periode: {$period}");
                Log::info("{$logPrefix} Memproses bisnis: {$business->name} (ID: {$business->id}) periode: {$period}");

                try {
                    $result = $this->financialService->processMonthlyClosing(
                        $period,
                        $business->id,
                        null,
                        $isDryRun
                    );

                    if ($isDryRun) {
                        $tableHeaders = ['Cabang', 'Kode', 'Omzet', 'HPP', 'Laba Kotor', 'Beban Ops', 'Laba Bersih'];
                        $tableRows = [];

                        foreach ($result['branches'] ?? [] as $b) {
                            $tableRows[] = [
                                $b['branch_name'] . ($b['is_main'] ? ' (Pusat)' : ''),
                                $b['branch_code'],
                                'Rp ' . number_format($b['monthly_income'], 0, ',', '.'),
                                'Rp ' . number_format($b['monthly_cogs'], 0, ',', '.'),
                                'Rp ' . number_format($b['gross_profit'], 0, ',', '.'),
                                'Rp ' . number_format($b['monthly_expense'], 0, ',', '.'),
                                'Rp ' . number_format($b['net_profit'], 0, ',', '.'),
                            ];
                        }

                        $this->table($tableHeaders, $tableRows);
                        $this->line("Total Laba Bersih Cabang : Rp " . number_format($result['total_branch_net'], 0, ',', '.'));
                        $this->line("Beban Holding/Owner     : Rp " . number_format($result['total_holding_expense'], 0, ',', '.'));
                        $this->info("Laba Bersih Konsolidasi : Rp " . number_format($result['consolidated_net_profit'], 0, ',', '.'));
                        continue;
                    }

                    $ownerProfit = $result;
                    $this->info(" - Rekonsiliasi cabang & holding tersimpan.");
                    $this->info(" - Laba bersih konsolidasi dihitung: Rp " . number_format($ownerProfit->net_profit, 0, ',', '.'));
                    Log::info("{$logPrefix} Laba bersih konsolidasi: Rp {$ownerProfit->net_profit}");

                    // Alokasi dana jika laba positif
                    if ($ownerProfit->net_profit > 0) {
                        $this->fundAllocationService->processAllocation([$ownerProfit->id]);
                        $this->info(" - Alokasi dana berhasil diproses.");
                        Log::info("{$logPrefix} Alokasi dana berhasil diproses.");
                    } else {
                        $ownerProfit->update(['status' => 'completed', 'allocated_at' => now()]);
                        $this->info(" - Profit nihil / minus (Rp " . number_format($ownerProfit->net_profit, 0, ',', '.') . "). Ditandai selesai.");
                        Log::info("{$logPrefix} Profit nihil / minus. Ditandai selesai.");
                    }

                } catch (\Exception $e) {
                    $this->error(" - Gagal memproses bisnis ID {$business->id}: " . $e->getMessage());
                    Log::error("{$logPrefix} Gagal memproses bisnis ID {$business->id}: " . $e->getMessage());
                }
            }

            $this->info("\nSemua proses selesai.");
            return 0;
        });
    }
}