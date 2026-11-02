<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmOrderService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');
        $rawModel = env('GEMINI_MODEL') ?: (config('services.gemini.model') ?? 'gemini-pro');
        if (empty($rawModel) || $rawModel === 'gemini-1.5-flash-latest') {
            $rawModel = 'gemini-pro';
        }
        $cleanModel = str_replace('GEMINI_MODEL=', '', $rawModel);
        $this->model = trim($cleanModel);
    }

    /**
     * Mem-parsing chat pelanggan WhatsApp menjadi JSON pesanan (jumlah_ball & ukuran_produk).
     * Urusan harga (harga_asumsi) ditiadakan sesuai workflow baru (Drag & Drop kasir).
     *
     * @param string $chatMessage Pesan masuk dari WhatsApp
     * @return array|null [jumlah_ball, ukuran_produk] atau null jika bukan format pesanan
     */
    public function parseOrder(string $chatMessage): ?array
    {
        // Guard Clause: Bukan pesanan (sapaan / pesan terlalu pendek)
        if (!$this->isPotentialOrder($chatMessage)) {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = "Pesan Pelanggan:\n\"{$chatMessage}\"\n\nEkstrak HANYA jumlah_ball dan ukuran_produk ke format JSON murni:";

        try {
            if (empty($this->apiKey)) {
                Log::warning("[LlmOrderService] GEMINI_API_KEY belum diisi di .env. Menggunakan parser rule-based fallback.");
                return $this->heuristicFallbackParser($chatMessage);
            }

            $rawResponse = $this->callGeminiApi($systemPrompt, $userPrompt);
            $parsed = $this->cleanAndDecodeJson($rawResponse);

            if ($parsed && isset($parsed['jumlah_ball'], $parsed['ukuran_produk'])) {
                return $this->normalizeParsedData($parsed, $chatMessage);
            }

            Log::warning("[LlmOrderService] Output Gemini tidak valid/kurang field, beralih ke fallback.", ['raw' => $rawResponse]);
            return $this->heuristicFallbackParser($chatMessage);
        } catch (\Throwable $e) {
            Log::error("[LlmOrderService] Gagal memanggil Gemini API: " . $e->getMessage());
            return $this->heuristicFallbackParser($chatMessage);
        }
    }

    /**
     * System Prompt Ketat: HANYA mengekstrak jumlah_ball dan ukuran_produk (TANPA HARGA).
     */
    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
Anda adalah AI Order Extractor resmi untuk sistem POS "BukuDig" (distributor agen es kristal).
Tugas Anda HANYA SATU: Ekstraksi jumlah ball (jumlah_ball) dan ukuran produk (ukuran_produk) dari chat WhatsApp pelanggan.
DILARANG KERAS mengekstrak harga, nominal uang, atau asumsi rupiah apapun.

FORMAT OUTPUT WAJIB (JSON murni tanpa markdown, tanpa teks pembuka/penutup):
{
  "jumlah_ball": <integer minimal 1>,
  "ukuran_produk": "<'20kg' | '15kg' | '10kg'>"
}

ATURAN WAJIB PEMETAAN UKURAN PRODUK:
1. Jika chat pelanggan mengandung angka/kata "19", "19rb", "19k", "yang 19", "yg 19" -> PAKSA ukuran_produk = "20kg".
2. Jika chat pelanggan mengandung angka/kata "15", "15rb", "15k", "yang 15", "yg 15" -> PAKSA ukuran_produk = "15kg".
3. Jika chat pelanggan mengandung angka/kata "10", "10rb", "10k", "yang 10", "yg 10" -> PAKSA ukuran_produk = "10kg".
4. Jika chat menyebutkan langsung ukuran spesifik: "20kg" -> "20kg", "15kg" -> "15kg", "10kg" -> "10kg".
5. Jika tidak ada angka ukuran di atas yang disebut, DEFAULT ukuran_produk = "20kg".

ATURAN JUMLAH BALL:
1. Angka kuantitas dihitung jika diawali/diikuti kata penanda: "ball", "sak", "karung", "biji", "bungkus", "pack" atau ada angka di depan pesanan (misal: "kirim 3 yang 19" -> jumlah_ball = 3, ukuran_produk = "20kg").
2. Angka "19", "15", "10" yang merujuk pada ukuran/pilihan es (contoh: "es kirim yang 19", "minta yang 19 dong") ADALAH UKURAN, BUKAN JUMLAH BALL!
3. Jika pelanggan tidak menyebut kuantitas dengan jelas (misal: "es kirim yang 19"), DEFAULT jumlah_ball = 1.
PROMPT;
    }

    /**
     * Melakukan HTTP Call ke Google Gemini API.
     */
    protected function callGeminiApi(string $systemPrompt, string $userPrompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $generationConfig = [
            'temperature' => 0.1,
        ];

        // responseMimeType hanya didukung pada model Gemini 1.5+
        if (str_contains($this->model, '1.5') || str_contains($this->model, '2.0')) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => $generationConfig,
        ];

        $response = Http::timeout(12)->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception("Gemini HTTP Error {$response->status()}: " . $response->body());
        }

        $result = $response->json();
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Membersihkan output markdown code fence dan decode JSON.
     */
    protected function cleanAndDecodeJson(string $raw): ?array
    {
        $clean = trim($raw);
        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        }
        if (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }
        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }

        $decoded = json_decode(trim($clean), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Normalisasi data hasil parsing dan penegakan aturan pemetaan ukuran.
     */
    protected function normalizeParsedData(array $data, string $originalChat): array
    {
        $jumlahBall = max(1, (int) ($data['jumlah_ball'] ?? 1));
        $lower = strtolower($originalChat);
        $ukuran = '20kg';

        // Penegakan Aturan Pemetaan Ukuran Wajib
        if (preg_match('/(?:^|\D)19(?:rb|k)?(?:\D|$)/i', $lower) || str_contains($lower, 'yang 19') || str_contains($lower, 'yg 19') || str_contains($lower, '20kg') || str_contains($lower, '20 kg')) {
            $ukuran = '20kg';
        } elseif (preg_match('/(?:^|\D)15(?:rb|k)?(?:\D|$)/i', $lower) || str_contains($lower, 'yang 15') || str_contains($lower, 'yg 15') || str_contains($lower, '15kg') || str_contains($lower, '15 kg')) {
            $ukuran = '15kg';
        } elseif (preg_match('/(?:^|\D)10(?:rb|k)?(?:\D|$)/i', $lower) || str_contains($lower, 'yang 10') || str_contains($lower, 'yg 10') || str_contains($lower, '10kg') || str_contains($lower, '10 kg')) {
            $ukuran = '10kg';
        } else {
            $parsedUkuran = strtolower(trim((string) ($data['ukuran_produk'] ?? '20kg')));
            $ukuran = in_array($parsedUkuran, ['20kg', '15kg', '10kg']) ? $parsedUkuran : '20kg';
        }

        return [
            'jumlah_ball'   => $jumlahBall,
            'ukuran_produk' => $ukuran,
        ];
    }

    /**
     * Pengecekan guard clause: mendeteksi apakah pesan berpotensi menjadi pesanan.
     * Mengembalikan false jika pesan hanya berupa sapaan/terlalu pendek tanpa angka atau kata kunci.
     */
    public function isPotentialOrder(string $chat): bool
    {
        $text = strtolower(trim($chat));

        // 1. Jika panjang kurang dari 3 karakter (misal "p", "y")
        if (strlen($text) < 3) {
            return false;
        }

        // 2. Cek apakah mengandung angka (0-9)
        $hasNumber = (bool) preg_match('/[0-9]/', $text);

        // 3. Cek apakah mengandung kata kunci pemesanan
        $keywords = ['es', 'ball', 'karung', 'sak', 'kirim', 'pesan'];
        $hasKeyword = false;
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                $hasKeyword = true;
                break;
            }
        }

        // Jika tidak mengandung satupun angka DAN tidak mengandung kata kunci, anggap bukan pesanan
        return $hasNumber || $hasKeyword;
    }

    /**
     * Heuristic Rule-Based Fallback Parser (Bila koneksi LLM timeout atau tanpa API Key).
     *
     * @param string $chat
     * @return array|null
     */
    public function heuristicFallbackParser(string $chat): ?array
    {
        // Guard Clause awal: Jika bukan format pesanan, return null
        if (!$this->isPotentialOrder($chat)) {
            return null;
        }

        $text = strtolower(trim($chat));

        // 1. Aturan Pemetaan Ukuran Wajib
        $ukuran = '20kg';
        if (preg_match('/(?:^|\D)19(?:rb|k)?(?:\D|$)/i', $text) || str_contains($text, 'yang 19') || str_contains($text, 'yg 19') || str_contains($text, '20kg')) {
            $ukuran = '20kg';
        } elseif (preg_match('/(?:^|\D)15(?:rb|k)?(?:\D|$)/i', $text) || str_contains($text, 'yang 15') || str_contains($text, 'yg 15') || str_contains($text, '15kg')) {
            $ukuran = '15kg';
        } elseif (preg_match('/(?:^|\D)10(?:rb|k)?(?:\D|$)/i', $text) || str_contains($text, 'yang 10') || str_contains($text, 'yg 10') || str_contains($text, '10kg')) {
            $ukuran = '10kg';
        }

        // 2. Deteksi Jumlah Ball
        $jumlah = 1;
        if (preg_match('/(\d+)\s*(?:ball|karung|sak|bungkus|pack|biji)/i', $text, $m)) {
            $jumlah = (int) $m[1];
        } elseif (preg_match('/(?:kirim|pesan|minta|order)\s+(\d+)\b/i', $text, $m)) {
            $candidate = (int) $m[1];
            if (!in_array($candidate, [19, 18, 15, 10])) {
                $jumlah = $candidate;
            }
        }

        return [
            'jumlah_ball'   => max(1, $jumlah),
            'ukuran_produk' => $ukuran,
        ];
    }
}
