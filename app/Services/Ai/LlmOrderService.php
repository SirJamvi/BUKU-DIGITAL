<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\TemporaryOrder;

class LlmOrderService
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        $this->model  = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest'));
    }

    /**
     * Mem-parsing pesan pelanggan WhatsApp menjadi ekstraksi slot JSON murni:
     * [nama_toko, alamat, jumlah_ball, ukuran_produk, waktu_pengiriman, is_registration, unknown_ukuran].
     *
     * LLM hanya bertugas mengekstrak data slot; balasan ke pelanggan dibentuk secara terpusat oleh PHP.
     *
     * @param string $combinedMessage Pesan gabungan (setelah debounce) dari WhatsApp
     * @param array $currentDraft Slot draft yang sudah terisi sebelumnya
     * @param string|null $awaiting Slot yang sedang dinantikan ('qty'|'nama_alamat'|'ukuran'|'waktu'|null)
     * @param bool $isNewCustomer Status pelanggan baru
     * @param string $customerName Nama pelanggan / toko
     * @param string $infoStok Informasi stok
     * @return array
     */
    public function parseOrder(
        string $combinedMessage,
        array $currentDraft = [],
        ?string $awaiting = null,
        bool $isNewCustomer = false,
        string $customerName = 'Kak',
        string $infoStok = ''
    ): array {
        // Cek guard clause: apakah pesan berpotensi mengandung pesanan / pendaftaran
        if (!$this->isPotentialOrder($combinedMessage, $isNewCustomer, $awaiting)) {
            return [
                'is_order'         => false,
                'nama_toko'        => null,
                'alamat'           => null,
                'jumlah_ball'      => null,
                'ukuran_produk'    => null,
                'waktu_pengiriman' => null,
                'is_registration'  => false,
                'unknown_ukuran'   => false,
            ];
        }

        // ==============================================================
        // FAST-PATH REGEX: Jalankan heuristik lebih dulu
        // ==============================================================
        $heuristic = $this->heuristicFallbackParser($combinedMessage, $currentDraft, $awaiting, $isNewCustomer);

        // Cek apakah slot sudah cukup untuk menghindari pemanggilan Gemini API:
        // 1. unknown_ukuran terdeteksi
        // 2. Pelanggan lama: jumlah_ball & ukuran_produk sudah terisi
        // 3. Pelanggan baru: jumlah_ball, ukuran_produk, nama_toko, & alamat sudah terisi
        // 4. Awaiting khusus (qty / ukuran) sudah terisi
        $hasQty = $heuristic['jumlah_ball'] !== null || !empty($currentDraft['jumlah_ball']);
        $hasUkuran = $heuristic['ukuran_produk'] !== null || !empty($currentDraft['ukuran_produk']);
        $hasName = !empty($heuristic['nama_toko']) || !empty($currentDraft['nama_toko']);
        $hasAddress = !empty($heuristic['alamat']) || !empty($currentDraft['alamat']);

        $slotCukup = false;
        if ($heuristic['unknown_ukuran']) {
            $slotCukup = true;
        } elseif (!$isNewCustomer && $hasQty && $hasUkuran) {
            $slotCukup = true;
        } elseif ($isNewCustomer && $hasQty && $hasUkuran && $hasName && $hasAddress) {
            $slotCukup = true;
        } elseif ($awaiting === 'qty' && $heuristic['jumlah_ball'] !== null) {
            $slotCukup = true;
        } elseif ($awaiting === 'ukuran' && $heuristic['ukuran_produk'] !== null) {
            $slotCukup = true;
        }

        if ($slotCukup) {
            Log::info("[AI-ORDER-PATH] regex: slot cukup, melewati pemanggilan Gemini.");
            return $heuristic;
        }

        Log::info("[AI-ORDER-PATH] gemini: memanggil Gemini API untuk ekstraksi teks bebas/alamat.");

        $systemPrompt = $this->buildSystemPrompt($currentDraft, $awaiting, $isNewCustomer, $infoStok);
        $userPrompt = "Pesan Pelanggan:\n\"{$combinedMessage}\"\n\nEkstrak slot JSON sesuai schema:";

        try {
            if (empty($this->apiKey)) {
                Log::warning("[LlmOrderService] GEMINI_API_KEY belum diisi di .env. Menggunakan rule-based fallback parser.");
                return $heuristic;
            }

            $rawResponse = $this->callGeminiApi($systemPrompt, $userPrompt);
            $parsed = $this->cleanAndDecodeJson($rawResponse);

            if (!$parsed || !is_array($parsed)) {
                Log::warning("[LlmOrderService] Output Gemini bukan JSON valid, menggunakan fallback.", ['raw' => $rawResponse]);
                return $heuristic;
            }

            Log::info("[LlmOrderService] Ekstraksi slot via Gemini API sukses.");
            return $this->normalizeParsedSlots($parsed, $combinedMessage, $awaiting);
        } catch (\Throwable $e) {
            Log::error("[LlmOrderService] Gagal memanggil Gemini API ({$e->getMessage()}), beralih ke fallback.");
            try {
                app(AdminGroupNotifier::class)->notifyGeminiFailure($e->getMessage());
            } catch (\Throwable $ignored) {}
            return $heuristic;
        }
    }

    /**
     * Membangun System Prompt yang ringkas dan ketat untuk ekstraksi slot JSON.
     */
    protected function buildSystemPrompt(
        array $currentDraft = [],
        ?string $awaiting = null,
        bool $isNewCustomer = false,
        string $infoStok = ''
    ): string {
        $nowWib = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
        $draftJson = json_encode($currentDraft, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Anda adalah asisten ekstraksi slot pesanan es kristal untuk Adin Crystal.
Tugas Anda HANYA mengekstrak data dari chat pelanggan ke dalam format JSON murni.
DILARANG menghasilkan teks balasan ke pelanggan. DILARANG mengekstrak harga/rupiah.

WAKTU SERVER SEKARANG (WIB): {$nowWib}
STATUS DRAFT SAAT INI: {$draftJson}
SLOT YANG SEDANG DITUNGGU (AWAITING): "{$awaiting}"
STATUS PELANGGAN BARU: "{$isNewCustomer}"

ATURAN EKSTRAKSI SLOT:
1. Slot yang diekstrak:
   - "nama_toko": string nama warung/toko/cafe/resto/depot bila disebutkan, atau null.
   - "alamat": string jalan/patokan alamat bila disebutkan, atau null.
   - "jumlah_ball": integer jumlah ball/karung bila disebutkan, atau null.
   - "ukuran_produk": HANYA "20kg", "15kg", atau "10kg", atau null.
   - "waktu_pengiriman": format "YYYY-MM-DD HH:MM:SS" bila pelanggan menyebut jam pengiriman, atau null.
   - "is_registration": boolean true jika pelanggan baru menyebutkan nama toko atau alamat.
   - "unknown_ukuran": boolean true jika pelanggan menyebutkan ukuran es selain 20kg/15kg/10kg (misalnya "yang 5", "5kg", "ukuran 5").

2. Aturan Angka & Ukuran:
   - Jika AWAITING = "qty", angka tunggal (contoh: "3", "lima") = jumlah_ball.
   - Jika AWAITING = "ukuran", angka (contoh: "20", "15", "10", "19") = ukuran_produk.
   - Pola "N ball/karung/sak/biji" = jumlah_ball.
   - Pemetaan ukuran resmi:
     * "19", "19rb", "19k", "yang 19", "20kg", "20" -> "20kg".
     * "15", "15rb", "15k", "yang 15", "15kg" -> "15kg".
     * "10", "10rb", "10k", "yang 10", "10kg" -> "10kg".
     * Jika ukuran tidak disebut sama sekali dan pelanggan memesan es, default "20kg".
     * Jika pelanggan menyebut ukuran di luar 20/15/10 (contoh: "yang 5"), set "ukuran_produk": null dan "unknown_ukuran": true. JANGAN PERNAH memetakan ukuran 5kg!

3. Aturan Waktu:
   - Konversikan "jam 4 sore", "besok jam 10 pagi", "nanti jam 2 siang" ke datetime WIB.
   - Jika jam yang disebut hari ini sudah terlewat dari jam server sekarang, anggap untuk besok.

SCHEMA OUTPUT WAJIB (JSON murni):
{
  "nama_toko": string|null,
  "alamat": string|null,
  "jumlah_ball": integer|null,
  "ukuran_produk": "20kg"|"15kg"|"10kg"|null,
  "waktu_pengiriman": string|null,
  "is_registration": boolean,
  "unknown_ukuran": boolean
}
PROMPT;
    }

    /**
     * Memanggil Google Gemini API dengan timeout 8 detik dan 1x retry cepat.
     */
    protected function callGeminiApi(string $systemPrompt, string $userPrompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'        => 0,
                'maxOutputTokens'    => 300,
                'responseMimeType'   => 'application/json',
                'thinkingConfig'     => [
                    'thinkingBudget' => 0,
                ],
            ],
        ];

        $startTime = microtime(true);

        // Timeout 12 detik dengan 1x retry (delay 100ms)
        $response = Http::timeout(12)
            ->retry(1, 100)
            ->post($url, $payload);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("[AI-ORDER-LATENCY] Gemini API duration: {$durationMs}ms");

        if (!$response->successful()) {
            throw new \Exception("Gemini HTTP Error {$response->status()}: " . $response->body());
        }

        $result = $response->json();
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Bersihkan Markdown code fence dan decode string JSON.
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
     * Normalisasi dan penegakan batas integritas pada slot hasil ekstraksi.
     */
    public function normalizeParsedSlots(array $parsed, string $originalText, ?string $awaiting = null): array
    {
        $textLower = strtolower($originalText);

        $namaToko = !empty($parsed['nama_toko']) ? trim(substr((string) $parsed['nama_toko'], 0, 100)) : null;
        $alamat = !empty($parsed['alamat']) ? trim(substr((string) $parsed['alamat'], 0, 255)) : null;

        // Cek ukuran yang tidak didukung (misal: "yang 5", "5kg", "5rb")
        $unknownUkuran = !empty($parsed['unknown_ukuran']);
        if (preg_match('/(?:^|\D)5(?:rb|k|kg)?(?:\D|$)/i', $textLower) && (str_contains($textLower, 'yang 5') || str_contains($textLower, 'yg 5') || str_contains($textLower, '5kg') || str_contains($textLower, '5 kg'))) {
            $unknownUkuran = true;
        }

        // Penegakan ukuran produk: HANYA 20kg, 15kg, 10kg
        $ukuran = null;
        if (!$unknownUkuran) {
            if (preg_match('/(?:^|\D)19(?:rb|k)?(?:\D|$)/i', $textLower) || str_contains($textLower, 'yang 19') || str_contains($textLower, 'yg 19') || str_contains($textLower, '20kg') || str_contains($textLower, '20 kg')) {
                $ukuran = '20kg';
            } elseif (preg_match('/(?:^|\D)15(?:rb|k)?(?:\D|$)/i', $textLower) || str_contains($textLower, 'yang 15') || str_contains($textLower, 'yg 15') || str_contains($textLower, '15kg') || str_contains($textLower, '15 kg')) {
                $ukuran = '15kg';
            } elseif (preg_match('/(?:^|\D)10(?:rb|k)?(?:\D|$)/i', $textLower) || str_contains($textLower, 'yang 10') || str_contains($textLower, 'yg 10') || str_contains($textLower, '10kg') || str_contains($textLower, '10 kg')) {
                $ukuran = '10kg';
            } else {
                $candidate = strtolower(trim((string) ($parsed['ukuran_produk'] ?? '')));
                if (in_array($candidate, ['20kg', '15kg', '10kg'])) {
                    $ukuran = $candidate;
                }
            }
        }

        // Jumlah ball
        $jumlahBall = null;
        if (!empty($parsed['jumlah_ball']) && (int) $parsed['jumlah_ball'] >= 1) {
            $jumlahBall = (int) $parsed['jumlah_ball'];
        } elseif ($awaiting === 'qty' && preg_match('/^\s*(\d+)\s*$/', $textLower, $qm)) {
            $jumlahBall = (int) $qm[1];
        } elseif (preg_match('/(\d+)\s*(?:ball|karung|sak|bungkus|pack|biji)/i', $textLower, $bm)) {
            $jumlahBall = (int) $bm[1];
        }

        // Waktu pengiriman
        $waktuPengiriman = null;
        if (!empty($parsed['waktu_pengiriman'])) {
            try {
                $dt = Carbon::parse($parsed['waktu_pengiriman'])->setTimezone('Asia/Jakarta');
                $waktuPengiriman = $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $waktuPengiriman = null;
            }
        }

        return [
            'is_order'         => ($jumlahBall !== null || $ukuran !== null || $unknownUkuran || !empty($namaToko)),
            'nama_toko'        => $namaToko,
            'alamat'           => $alamat,
            'jumlah_ball'      => $jumlahBall,
            'ukuran_produk'    => $ukuran,
            'waktu_pengiriman' => $waktuPengiriman,
            'is_registration'  => !empty($parsed['is_registration']) || !empty($namaToko) || !empty($alamat),
            'unknown_ukuran'   => $unknownUkuran,
        ];
    }

    /**
     * Fallback Heuristik Regex saat koneksi LLM mengalami kendala.
     * Tidak membuang konteks draft dan mampu menginterpretasikan slot dengan akurat.
     */
    public function heuristicFallbackParser(
        string $chat,
        array $currentDraft = [],
        ?string $awaiting = null,
        bool $isNewCustomer = false
    ): array {
        Log::info("[LlmOrderService] Menjalankan heuristic regex fallback parser.");
        $text = strtolower(trim($chat));

        // 1. Cek ukuran tidak dikenal (misal "yang 5")
        $unknownUkuran = false;
        if (preg_match('/(?:^|\D)5(?:rb|k|kg)?(?:\D|$)/i', $text) && (str_contains($text, 'yang 5') || str_contains($text, 'yg 5') || str_contains($text, '5kg') || str_contains($text, '5 kg'))) {
            $unknownUkuran = true;
        }

        // 2. Deteksi Ukuran yang Didukung
        // HATI-HATI: Angka 10/15/19 yang didahului kata "jam" atau diikuti "ball/karung/sak" BUKAN ukuran!
        $ukuran = null;
        if (!$unknownUkuran) {
            if ($awaiting === 'ukuran') {
                if (preg_match('/(?:20|19)/', $text)) {
                    $ukuran = '20kg';
                } elseif (str_contains($text, '15')) {
                    $ukuran = '15kg';
                } elseif (str_contains($text, '10')) {
                    $ukuran = '10kg';
                }
            } else {
                if (
                    str_contains($text, 'yang 19') || str_contains($text, 'yg 19') || str_contains($text, '20kg') || str_contains($text, '20 kg') ||
                    preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b19(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $text)
                ) {
                    $ukuran = '20kg';
                } elseif (
                    str_contains($text, 'yang 15') || str_contains($text, 'yg 15') || str_contains($text, '15kg') || str_contains($text, '15 kg') ||
                    preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b15(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $text)
                ) {
                    $ukuran = '15kg';
                } elseif (
                    str_contains($text, 'yang 10') || str_contains($text, 'yg 10') || str_contains($text, '10kg') || str_contains($text, '10 kg') ||
                    preg_match('/(?<!jam\s)(?<!jam\s0)(?<!jam\s1)\b10(?:rb|k)?\b(?!\s*(?:ball|karung|sak))/i', $text)
                ) {
                    $ukuran = '10kg';
                }
            }
        }

        // 3. Deteksi Jumlah Ball
        $jumlah = null;
        if ($awaiting === 'qty' && preg_match('/^\s*(\d+)\s*$/', $text, $qm)) {
            $jumlah = (int) $qm[1];
        } elseif (preg_match('/(\d+)\s*(?:ball|karung|sak|bungkus|pack|biji)/i', $text, $m)) {
            $jumlah = (int) $m[1];
        } elseif (preg_match('/(?:kirim|pesan|minta|order|pesen)\s+(\d+)\b/i', $text, $m)) {
            $candidate = (int) $m[1];
            // Pastikan bukan angka ukuran 19, 15, 10
            if (!in_array($candidate, [19, 15, 10])) {
                $jumlah = $candidate;
            }
        }

        // Jika ada kata 'pesan' atau 'es' tapi jumlah belum terdeteksi dan bukan awaiting ukuran
        if ($jumlah === null && preg_match('/\b(?:pesen|pesan|kirim|minta)\s+es\s+(\d+)\b/i', $text, $m)) {
            $candidate = (int) $m[1];
            if (!in_array($candidate, [19, 15, 10])) {
                $jumlah = $candidate;
            }
        }

        // 4. Deteksi Waktu Pengiriman
        $waktuPengiriman = null;
        if (preg_match('/jam\s*(\d{1,2})(?::(\d{2}))?\s*(pagi|siang|sore|malam)?/i', $text, $timeMatch)) {
            try {
                $hour = (int) $timeMatch[1];
                $minute = !empty($timeMatch[2]) ? (int) $timeMatch[2] : 0;
                $period = strtolower($timeMatch[3] ?? '');

                if ($period === 'sore' && $hour < 12) {
                    $hour += 12;
                } elseif ($period === 'malam' && $hour < 12) {
                    $hour += 12;
                } elseif ($period === 'siang' && $hour < 11) {
                    $hour += 12;
                }

                $targetDate = now()->setTimezone('Asia/Jakarta');
                if (str_contains($text, 'besok') || $hour < (int) $targetDate->format('H')) {
                    $targetDate->addDay();
                }
                $targetDate->setTime($hour, $minute, 0);
                $waktuPengiriman = $targetDate->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        // 5. Deteksi Nama Toko & Alamat Sederhana
        $namaToko = null;
        $alamat = null;
        if ($isNewCustomer || $awaiting === 'nama_alamat') {
            // Cek kata kunci toko/cafe/caffe/resto
            if (preg_match('/(?:toko|cafe|kafe|caffe|kedai|warung|resto|depot|kopi)\s+([^,\n\.]+)/i', $chat, $tm)) {
                $namaToko = trim(substr($tm[0], 0, 100));
            }
            if (preg_match('/(?:jl\.?|jalan|alamat|di\s+daerah|di)\s+([^,\n]+)/i', $chat, $am)) {
                $alamat = trim(substr($am[0], 0, 255));
            }

            // Jika sedang awaiting nama_alamat dan dipisahkan koma atau baris baru: "Nama Toko, Alamat"
            if (empty($namaToko) && empty($alamat) && $awaiting === 'nama_alamat') {
                if (str_contains($chat, ',')) {
                    $parts = explode(',', $chat, 2);
                    $namaToko = trim($parts[0]);
                    $alamat = trim($parts[1]);
                } elseif (!preg_match('/[0-9]/', $chat) && strlen(trim($chat)) > 2 && strlen(trim($chat)) <= 40) {
                    if (str_starts_with(strtolower(trim($chat)), 'di ')) {
                        $alamat = trim($chat);
                    } else {
                        $namaToko = trim($chat);
                    }
                }
            }
        }

        return [
            'is_order'         => ($jumlah !== null || $ukuran !== null || $unknownUkuran || !empty($namaToko)),
            'nama_toko'        => $namaToko,
            'alamat'           => $alamat,
            'jumlah_ball'      => $jumlah,
            'ukuran_produk'    => $ukuran,
            'waktu_pengiriman' => $waktuPengiriman,
            'is_registration'  => !empty($namaToko) || !empty($alamat),
            'unknown_ukuran'   => $unknownUkuran,
        ];
    }

    /**
     * Hitung sisa stok tersedia secara real-time dengan memperhitungkan pesanan berstatus 'validated' (Soft Reservation).
     *
     * @param string $ukuran Ukuran produk (20kg, 15kg, 10kg)
     * @return array [actual_stock, reserved_stock, available_stock]
     */
    public function getAvailableStockWithReservation(string $ukuran): array
    {
        $cleanUkuran = str_replace('kg', '', strtolower($ukuran));
        $actualStock = 0;

        try {
            $product = Product::where('name', 'like', "%{$cleanUkuran}%")
                ->with('inventory')
                ->first();

            if ($product) {
                if ($product->inventory && isset($product->inventory->current_stock)) {
                    $actualStock = (int) $product->inventory->current_stock;
                } elseif (isset($product->stok)) {
                    $actualStock = (int) $product->stok;
                } elseif (isset($product->current_stock)) {
                    $actualStock = (int) $product->current_stock;
                }
            } else {
                Log::warning("[LlmOrderService] Produk es ukuran {$ukuran} tidak ditemukan di database.");
            }
        } catch (\Throwable $e) {
            Log::warning("[LlmOrderService] Gagal membaca stok produk {$ukuran}: " . $e->getMessage());
        }

        // Lazy-expiry pesanan validated > 24 jam menjadi expired
        try {
            TemporaryOrder::lazyExpireOldOrders();
        } catch (\Throwable $e) {
            Log::warning("[LlmOrderService] Gagal menjalankan lazyExpireOldOrders: " . $e->getMessage());
        }

        // Hitung total ball order berstatus 'validated' dalam 24 jam terakhir (Soft Reservation)
        $reserved = (int) TemporaryOrder::where('status', 'validated')
            ->where('ukuran_produk', $ukuran)
            ->where('created_at', '>=', now()->subHours(24))
            ->sum('jumlah_ball');

        $availableStock = max(0, $actualStock - $reserved);

        return [
            'actual'    => $actualStock,
            'reserved'  => $reserved,
            'available' => $availableStock,
        ];
    }

    /**
     * Dual-Layer Stock Guard: Memeriksa apakah jumlah pesanan melebihi stok yang tersedia.
     */
    public function isStockExceeded(int $jumlahBall, string $ukuran, ?int &$availableStock = null): bool
    {
        $stockData = $this->getAvailableStockWithReservation($ukuran);
        $availableStock = $stockData['available'];

        return $jumlahBall > $availableStock;
    }

    /**
     * Output Filter: Mencegah kebocoran angka stok riil pada teks balasan apapun.
     */
    public function filterStockNumbers(string $text, int $actualStock): string
    {
        if ($actualStock <= 0) {
            return $text;
        }

        // Jika angka stok riil muncul di teks, bersihkan / ganti
        $pattern = '/\b' . $actualStock . '\b/';
        if (preg_match($pattern, $text)) {
            return preg_replace($pattern, 'beberapa', $text);
        }

        return $text;
    }

    /**
     * Pengecekan guard clause cepat: apakah pesan berpotensi mengandung pesanan / pendaftaran.
     */
    public function isPotentialOrder(string $chat, bool $isNewCustomer = false, ?string $awaiting = null): bool
    {
        $text = strtolower(trim($chat));

        if (strlen($text) < 1) {
            return false;
        }

        // Jika sedang awaiting sesuatu, teks pendek apa pun berpotensi menjadi jawaban
        if (!empty($awaiting)) {
            return true;
        }

        // Cek angka
        if (preg_match('/[0-9]/', $text)) {
            return true;
        }

        // Kata kunci pesanan
        $keywords = ['es', 'ball', 'karung', 'sak', 'kirim', 'pesan', 'pesen', 'minta', 'order', 'toko', 'cafe', 'kafe', 'warung', 'alamat', 'jl'];
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }
}
