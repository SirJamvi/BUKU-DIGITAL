<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Admin\ReportService;
use App\Mail\ReportGenerated;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

class GenerateReports extends Command
{
    /**
     * Nama dan signature dari console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate {--type=daily : Tipe laporan (daily atau monthly)}';

    /**
     * Deskripsi dari console command.
     *
     * @var string
     */
    protected $description = 'Membuat dan mengirim laporan penjualan dan finansial secara otomatis.';

    /**
     * @var ReportService
     */
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Menjalankan console command.
     *
     * @return int
     */
    public function handle()
    {
        $reportType = $this->option('type');
        $this->info("Memulai pembuatan laporan {$reportType}...");

        try {
            // Tentukan rentang tanggal berdasarkan tipe laporan
            $filters = $this->getDateFilters($reportType);

            // Buat laporan
            $salesReport = $this->reportService->getSalesReport($filters);
            $financialReport = $this->reportService->getFinancialReport($filters);

            // Kirim laporan ke semua admin
            $admins = User::where('role', 'admin')->where('is_active', true)->get();
            if ($admins->isEmpty()) {
                $this->warn('Tidak ada admin aktif yang ditemukan untuk dikirimi laporan.');
                return 1;
            }

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new ReportGenerated($salesReport, $financialReport, $reportType));
                $this->info("Laporan berhasil dikirim ke: {$admin->email}");
            }

            $this->info('Semua laporan telah berhasil dibuat dan dikirim.');
            return 0;

        } catch (\Exception $e) {
            $this->error("Terjadi kesalahan saat membuat laporan: {$e->getMessage()}");
            logger()->error('GenerateReports Command Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Helper untuk menentukan filter tanggal.
     */
    private function getDateFilters(string $type): array
    {
        if ($type === 'monthly') {
            return [
                'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
            ];
        }

        // Default adalah harian
        return [
            'start_date' => now()->subDay()->startOfDay()->toDateString(),
            'end_date' => now()->subDay()->endOfDay()->toDateString(),
        ];
    }
}