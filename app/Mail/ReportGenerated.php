<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportGenerated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $salesReport;
    public $financialReport;
    public $reportType;

    /**
     * Buat instance pesan baru.
     *
     * @param array $salesReport
     * @param array $financialReport
     * @param string $reportType
     */
    public function __construct(array $salesReport, array $financialReport, string $reportType)
    {
        $this->salesReport = $salesReport;
        $this->financialReport = $financialReport;
        $this->reportType = $reportType;
    }

    /**
     * Dapatkan "amplop" pesan.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Laporan ' . ucfirst($this->reportType) . ' Telah Dibuat',
        );
    }

    /**
     * Dapatkan konten definisi pesan.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reports.generated',
        );
    }

    /**
     * Dapatkan lampiran untuk pesan.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Anda bisa menambahkan lampiran PDF atau Excel di sini jika perlu
        return [];
    }
}