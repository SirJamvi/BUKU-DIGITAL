<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Admin\ReportService;
use App\Mail\ReportGenerated;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Exception;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    protected $reportType;
    /**
     * @var array
     */
    protected $filters;
    /**
     * @var User
     */
    protected $admin;

    /**
     * Buat instance job baru.
     *
     * @param string $reportType
     * @param array $filters
     * @param User $admin
     */
    public function __construct(string $reportType, array $filters, User $admin)
    {
        $this->reportType = $reportType;
        $this->filters = $filters;
        $this->admin = $admin;
    }

    /**
     * Jalankan job.
     *
     * @param ReportService $reportService
     * @return void
     */
    public function handle(ReportService $reportService): void
    {
        try {
            // Buat laporan berdasarkan tipe
            $salesReport = $reportService->getSalesReport($this->filters);
            $financialReport = $reportService->getFinancialReport($this->filters);

            // Kirim email ke admin yang bersangkutan
            Mail::to($this->admin->email)->send(new ReportGenerated($salesReport, $financialReport, $this->reportType));

        } catch (Exception $e) {
            logger()->error("Gagal menjalankan job GenerateReport untuk user: {$this->admin->email}. Error: " . $e->getMessage());
        }
    }
}