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
     * Signature diubah untuk menerima --period opsional
     */
    protected $signature = 'app:process-monthly-closing {--period= : Periode yang akan diproses (format: YYYY-MM)}';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Menjalankan proses tutup buku dan alokasi dana bulanan secara otomatis untuk semua bisnis.';

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
        Log::info('[AutoClosing] Memulai proses tutup buku dan alokasi dana...');
        
        // =======================================================
        // PERUBAHAN LOGIKA PERIODE
        // =======================================================
        // Ambil periode dari input, jika tidak ada, gunakan bulan lalu
        $periodInput = $this->option('period');
        
        if ($periodInput) {
            $period = $periodInput;
            $this->warn("Menjalankan proses manual untuk periode: $period");
            Log::info("[AutoClosing] Menjalankan proses manual untuk periode: $period");
        } else {
            $period = Carbon::now()->subMonth()->format('Y-m');
            $this->info("Menjalankan proses otomatis untuk periode: $period");
            Log::info("[AutoClosing] Menjalankan proses otomatis untuk periode: $period");
        }
        // =======================================================
        
        // Ambil semua bisnis yang aktif di sistem Anda
        $businesses = Business::all();

        foreach ($businesses as $business) {
            $this->info("Memproses bisnis: {$business->name} (ID: {$business->id}) untuk periode: $period");
            Log::info("[AutoClosing] Memproses bisnis: {$business->name} (ID: {$business->id}) untuk periode: $period");
            
            try {
                // 1. Login sebagai owner bisnis untuk simulasi Auth::user()
                Auth::guard('web')->loginUsingId($business->owner_id);

                // 2. Jalankan "Tutup Buku"
                $ownerProfit = $this->financialService->processMonthlyClosing($period);

                $this->info(" - Laba bersih dihitung: Rp {$ownerProfit->net_profit}");
                Log::info(" - Laba bersih dihitung: Rp {$ownerProfit->net_profit}");

                // 3. Jika ada profit, langsung jalankan "Alokasi Dana"
                if ($ownerProfit->net_profit > 0) {
                    $this->fundAllocationService->processAllocation([$ownerProfit->id]);
                    $this->info(" - Alokasi dana berhasil diproses.");
                    Log::info(" - Alokasi dana berhasil diproses.");
                } else {
                    // Jika profit 0 atau minus, tetap tandai 'completed' agar tidak menumpuk
                    $ownerProfit->update(['status' => 'completed', 'allocated_at' => now()]);
                    $this->info(" - Tidak ada profit untuk dialokasikan (Rp {$ownerProfit->net_profit}). Ditandai selesai.");
                    Log::info(" - Tidak ada profit untuk dialokasikan (Rp {$ownerProfit->net_profit}). Ditandai selesai.");
                }

                // 4. Logout setelah selesai
                Auth::guard('web')->logout();

            } catch (\Exception $e) {
                $this->error(" - Gagal memproses bisnis ID {$business->id}: " . $e->getMessage());
                Log::error("[AutoClosing] Gagal memproses bisnis ID {$business->id}: " . $e->getMessage());
                
                // Pastikan logout jika terjadi error
                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }
            }
        }
        
        Log::info('[AutoClosing] Semua proses bulanan selesai.');
        $this->info('Semua proses tutup buku dan alokasi bulanan telah selesai.');
        return 0;
    }
}