<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = config('services.fonnte.token') ?? env('FONNTE_TOKEN', '');
        $this->apiUrl = config('services.fonnte.api_url') ?? env('FONNTE_API_URL', 'https://api.fonnte.com/send');
    }

    /**
     * Mengirim pesan WhatsApp ke nomor tujuan melalui Fonnte Gateway.
     *
     * @param string $target Nomor penerima (contoh: 08123456789 atau 628123456789)
     * @param string $message Isi pesan WhatsApp
     * @return array
     */
    public function sendMessage(string $target, string $message): array
    {
        if (empty($this->token)) {
            Log::warning("[FonnteService] FONNTE_TOKEN belum diatur di .env. Simulasi pesan ke {$target}: \n{$message}");
            return [
                'status'    => true,
                'simulated' => true,
                'message'   => 'Simulasi pengiriman berhasil (Token belum diisi di .env)',
            ];
        }

        try {
            if (!str_contains($target, '@g.us')) {
                // normalisasi nomor biasa (hapus karakter non-angka)
                $cleanTarget = preg_replace('/[^0-9]/', '', $target);
            } else {
                // Target grup WhatsApp: pertahankan format JID grup (termasuk akhiran @g.us)
                $cleanTarget = trim($target);
            }

            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->timeout(12)->post($this->apiUrl, [
                'target'      => $cleanTarget,
                'message'     => $message,
                'countryCode' => '62',
                'delay'       => '2-4',
            ]);

            $result = $response->json() ?? [];
            $isSuccess = $response->successful() && ($result['status'] ?? true);

            Log::info("[FonnteService] Kirim WA ke {$cleanTarget} - Status HTTP: {$response->status()}", [
                'response' => $result,
            ]);

            return [
                'status'   => $isSuccess,
                'response' => $result,
            ];
        } catch (\Throwable $e) {
            Log::error("[FonnteService] Gagal kirim WA ke {$target}: " . $e->getMessage());
            return [
                'status' => false,
                'error'  => $e->getMessage(),
            ];
        }
    }
}
