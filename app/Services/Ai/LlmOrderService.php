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
        $this->apiKey = config('services.gemini.key');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash-latest');
    }

    /**
     * Mem-parsing chat pelanggan WhatsApp menjadi JSON pesanan (jumlah_ball & ukuran_produk)
     * atau data registrasi pelanggan baru (nama_toko & alamat).
     *
     * @param string $chatMessage Pesan masuk dari WhatsApp
     * @param bool $isNewCustomer Status apakah pengirim pelanggan baru
     * @param string $customerName Nama panggilan pengirim
     * @param string $infoStok Informasi stok real-time saat ini
     * @return array|null [jumlah_ball, ukuran_produk] atau [is_registration, ...] atau [is_out_of_stock, reply] atau null
     */
    public function parseOrder(string $chatMessage, bool $isNewCustomer = false, string $customerName = 'Kak', string $infoStok = ''): ?array
    {
        // Guard Clause: Bukan pesanan atau data registrasi
        if (!$this->isPotentialOrder($chatMessage, $isNewCustomer)) {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt($isNewCustomer, $customerName, $infoStok);
        $userPrompt = "Pesan Pelanggan:\n\"{$chatMessage}\"\n\nProses sesuai aturan:";

        try {
            if (empty($this->apiKey)) {
                Log::warning("[LlmOrderService] GEMINI_API_KEY belum diisi di .env. Menggunakan parser rule-based fallback.");
                return $this->heuristicFallbackParser($chatMessage, $isNewCustomer, $customerName, $infoStok);
            }

            $rawResponse = $this->callGeminiApi($systemPrompt, $userPrompt);
            $parsed = $this->cleanAndDecodeJson($rawResponse);

            // 1. Cek jika AI mengembalikan teks biasa (misal penolakan stok / out of stock)
            if (!$parsed && !empty($rawResponse) && !str_starts_with(trim($rawResponse), '{')) {
                return [
                    'is_out_of_stock' => true,
                    'reply'           => trim($rawResponse),
                ];
            }

            // 2. Cek jika AI mengembalikan JSON penolakan stok / balasan out of stock
            if ($parsed && !empty($parsed['balasan']) && empty($parsed['jumlah_ball']) && !$isNewCustomer) {
                return [
                    'is_out_of_stock' => true,
                    'reply'           => $parsed['balasan'],
                ];
            }

            // 3. Cek jika AI mengekstrak data registrasi pelanggan baru (mendukung One-Step Registration)
            if ($parsed && isset($parsed['is_registration']) && ($parsed['is_registration'] === true || $parsed['is_registration'] === 'true')) {
                $jumlahBall = !empty($parsed['jumlah_ball']) ? (int) $parsed['jumlah_ball'] : null;
                $ukuranProduk = !empty($parsed['ukuran_produk']) ? (string) $parsed['ukuran_produk'] : null;
                $waktuPengiriman = !empty($parsed['waktu_pengiriman']) ? (string) $parsed['waktu_pengiriman'] : null;

                // Jika One-Step Registration memuat pesanan sekalian
                if ($jumlahBall && $ukuranProduk) {
                    $norm = $this->normalizeParsedData([
                        'jumlah_ball'      => $jumlahBall,
                        'ukuran_produk'    => $ukuranProduk,
                        'waktu_pengiriman' => $waktuPengiriman,
                    ], $chatMessage);

                    $jumlahBall = $norm['jumlah_ball'];
                    $ukuranProduk = $norm['ukuran_produk'];
                    $waktuPengiriman = $norm['waktu_pengiriman'];

                    // Validasi sisa stok pada One-Step Registration
                    if ($this->isStockExceeded($jumlahBall, $ukuranProduk, $infoStok, $availableStock)) {
                        return [
                            'is_out_of_stock' => true,
                            'reply'           => "Maaf {$customerName}, kebetulan stok es ukuran tersebut saat ini sedang kurang dari permintaan Kakak. Silakan kurangi jumlah pesanan, atau ketik *admin* agar kami dapat mengecek jadwal produksi selanjutnya.",
                        ];
                    }
                }

                return [
                    'is_registration'  => true,
                    'nama_toko'        => $parsed['nama_toko'] ?? '',
                    'alamat'           => $parsed['alamat'] ?? '',
                    'jumlah_ball'      => $jumlahBall,
                    'ukuran_produk'    => $ukuranProduk,
                    'waktu_pengiriman' => $waktuPengiriman,
                ];
            }

            // 4. Jika pelanggan baru namun belum memberikan data lengkap / AI meminta data
            if ($isNewCustomer) {
                $balasan = $parsed['balasan'] ?? $parsed['reply'] ?? $parsed['message'] ?? null;
                if (!empty($balasan)) {
                    return [
                        'is_new_customer' => true,
                        'reply'           => $balasan,
                    ];
                }

                return [
                    'is_new_customer' => true,
                    'reply'           => "Halo {$customerName}! Sebelum kami dapat memproses pesanan Anda, mohon kirimkan Nama Toko/Cafe dan Alamat Lengkap Anda terlebih dahulu ya.",
                ];
            }

            // 5. Pelanggan lama: pesanan normal
            if ($parsed && isset($parsed['jumlah_ball'], $parsed['ukuran_produk'])) {
                $normalized = $this->normalizeParsedData($parsed, $chatMessage);

                // Validasi sisa stok pada pesanan pelanggan lama
                if ($this->isStockExceeded($normalized['jumlah_ball'], $normalized['ukuran_produk'], $infoStok, $availableStock)) {
                    return [
                        'is_out_of_stock' => true,
                        'reply'           => "Maaf {$customerName}, kebetulan stok es ukuran tersebut saat ini sedang kurang dari permintaan Kakak. Silakan kurangi jumlah pesanan, atau ketik *admin* agar kami dapat mengecek jadwal produksi selanjutnya.",
                    ];
                }

                return $normalized;
            }

            Log::warning("[LlmOrderService] Output Gemini tidak valid/kurang field, beralih ke fallback.", ['raw' => $rawResponse]);
            return $this->heuristicFallbackParser($chatMessage, $isNewCustomer, $customerName, $infoStok);
        } catch (\Throwable $e) {
            Log::error("[LlmOrderService] Gagal memanggil Gemini API: " . $e->getMessage());
            return $this->heuristicFallbackParser($chatMessage, $isNewCustomer, $customerName, $infoStok);
        }
    }

    /**
     * System Prompt AI: Mendukung Validasi Stok Real-time, One-Step Registration, dan Ekstraksi Pesanan.
     */
    protected function buildSystemPrompt(bool $isNewCustomer = false, string $customerName = 'Kak', string $infoStok = ''): string
    {
        $currentWib = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
        $stokHeader = !empty($infoStok) ? $infoStok : "Stok 15kg: Tersedia. Stok 20kg: Tersedia.";

        if ($isNewCustomer) {
            $customerStatusPrompt = <<<CUSTOMER_RULE
PENTING: Pengguna ini adalah PELANGGAN BARU.
1. Jika mereka belum memberikan Nama Toko/Cafe DAN Alamat Lengkap, tolak pesanan dengan sopan menyapa namanya ({$customerName}) dan minta data tersebut. Jangan merangkum pesanan atau meminta kata 'oke' sampai mereka memberikan data tersebut.
2. JIKA mereka SUDAH memberikan Nama Toko/Cafe dan Alamat (meskipun dalam kalimat yang sama dengan pesanan):
   - Jika dia juga menyebutkan niat pesanan (jumlah dan ukuran es), masukkan semuanya dalam SATU format JSON sekaligus (One-Step Registration) dengan key: "is_registration" (true), "nama_toko", "alamat", "jumlah_ball", "ukuran_produk", dan "waktu_pengiriman".
   - Jika dia HANYA memberikan data toko tanpa pesanan, masukkan key "is_registration" (true), "nama_toko", "alamat", dan set "jumlah_ball": null, "ukuran_produk": null.
CUSTOMER_RULE;
        } else {
            $customerStatusPrompt = "Pengguna ini adalah PELANGGAN LAMA. Proses pesanan seperti biasa, rangkum pesanan, tentukan waktu pengiriman, dan minta dia membalas 'oke' untuk konfirmasi.";
        }

        return <<<PROMPT
INFORMASI STOK SAAT INI: {$stokHeader}

ATURAN VALIDASI STOK:
PENTING: Sebelum memproses pesanan, bandingkan jumlah pesanan dengan INFORMASI STOK SAAT INI. Jika pesanan MELEBIHI sisa stok, JANGAN kembalikan format JSON pesanan. Tolak pesanan dengan sopan, TETAPI KAMU DILARANG KERAS MENYEBUTKAN ANGKA SISA STOK AKTUAL KEPADA PELANGGAN. Gunakan bahasa yang natural seperti: "Maaf Kak, kebetulan stok es ukuran tersebut saat ini sedang kurang dari permintaan Kakak." Di akhir kalimat WAJIB tambahkan: "Silakan kurangi jumlah pesanan, atau ketik *admin* agar kami dapat mengecek jadwal produksi selanjutnya."

ATURAN ONE-STEP REGISTRATION (UNTUK PELANGGAN BARU):
Jika pelanggan baru memberikan Nama Toko dan Alamat, cek riwayat percakapan sebelumnya atau pesan saat ini. Jika dia sebelumnya/saat ini sudah menyebutkan niat pesanan (jumlah dan ukuran), masukkan semuanya dalam SATU format JSON sekaligus: is_registration: true, nama_toko, alamat, SERTA jumlah_ball, ukuran_produk, dan delivery_time.

Anda adalah AI Order Extractor & Customer Registration Assistant resmi untuk sistem POS "BukuDig" (distributor agen es kristal).
Tugas Anda: Ekstraksi data pendaftaran pelanggan baru (jika pelanggan baru) ATAU ekstraksi jumlah ball (jumlah_ball), ukuran produk (ukuran_produk), dan waktu pengiriman (waktu_pengiriman) dari chat WhatsApp pelanggan.
DILARANG KERAS mengekstrak harga, nominal uang, atau asumsi rupiah apapun.

STATUS PELANGGAN:
{$customerStatusPrompt}

WAKTU SISTEM SAAT INI (WIB / Asia/Jakarta): {$currentWib}

FORMAT OUTPUT WAJIB (JSON murni tanpa markdown, tanpa teks pembuka/penutup):

1. JIKA PELANGGAN BARU MENDAFTAR SEKALIGUS MEMESAN (ONE-STEP REGISTRATION):
{
  "is_registration": true,
  "nama_toko": "<Nama Toko/Cafe/Warung>",
  "alamat": "<Alamat Lengkap>",
  "jumlah_ball": <integer minimal 1>,
  "ukuran_produk": "<'20kg' | '15kg' | '10kg'>",
  "waktu_pengiriman": "<'YYYY-MM-DD HH:MM:SS' | null>"
}

2. JIKA PELANGGAN BARU HANYA MEMBERIKAN NAMA TOKO DAN ALAMAT (TANPA PESANAN):
{
  "is_registration": true,
  "nama_toko": "<Nama Toko/Cafe/Warung>",
  "alamat": "<Alamat Lengkap>",
  "jumlah_ball": null,
  "ukuran_produk": null,
  "waktu_pengiriman": null
}

3. JIKA PELANGGAN BARU BELUM MEMBERIKAN DATA LENGKAP:
{
  "is_registration": false,
  "balasan": "<Pesan sopan menyapa {$customerName} dan meminta Nama Toko/Cafe serta Alamat Lengkap>"
}

4. JIKA PELANGGAN LAMA (PESANAN NORMAL):
{
  "is_registration": false,
  "jumlah_ball": <integer minimal 1>,
  "ukuran_produk": "<'20kg' | '15kg' | '10kg'>",
  "waktu_pengiriman": "<'YYYY-MM-DD HH:MM:SS' | null>"
}

ATURAN WAKTU PENGIRIMAN (waktu_pengiriman):
- Jika pelanggan menyebutkan waktu spesifik pengiriman (misal: 'jam 4 sore', 'besok jam 10 pagi', 'nanti jam 2 siang'), konversikan waktu tersebut ke format datetime 'YYYY-MM-DD HH:MM:SS' berdasarkan waktu saat ini (WIB/Asia/Jakarta).
- Jika tidak ada keterangan waktu pengiriman, set null.
- Tambahkan key 'waktu_pengiriman' pada JSON output.

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
     * Memeriksa apakah jumlah pesanan melebihi stok yang ada di string infoStok.
     */
    protected function isStockExceeded(int $jumlahBall, string $ukuran, string $infoStok, ?int &$availableStock = null): bool
    {
        if (empty($infoStok)) {
            return false;
        }

        $cleanUkuran = str_replace('kg', '', strtolower($ukuran));
        if (preg_match('/Stok\s*' . preg_quote($cleanUkuran, '/') . 'kg?:\s*(\d+)/i', $infoStok, $m)) {
            $availableStock = (int) $m[1];
            return $jumlahBall > $availableStock;
        }

        return false;
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

        $response = Http::timeout(60)->post($url, $payload);

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

        $waktuPengiriman = null;
        if (!empty($data['waktu_pengiriman'])) {
            try {
                $waktuPengiriman = \Carbon\Carbon::parse($data['waktu_pengiriman'])->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $waktuPengiriman = null;
            }
        }

        return [
            'jumlah_ball'      => $jumlahBall,
            'ukuran_produk'    => $ukuran,
            'waktu_pengiriman' => $waktuPengiriman,
        ];
    }

    /**
     * Pengecekan guard clause: mendeteksi apakah pesan berpotensi menjadi pesanan atau data registrasi.
     */
    public function isPotentialOrder(string $chat, bool $isNewCustomer = false): bool
    {
        $text = strtolower(trim($chat));

        // 1. Jika panjang kurang dari 3 karakter (misal "p", "y")
        if (strlen($text) < 3) {
            return false;
        }

        // Jika pelanggan baru, cek juga kemungkinan pesan berisi nama toko & alamat pendaftaran
        if ($isNewCustomer) {
            $regKeywords = ['toko', 'cafe', 'kafe', 'warung', 'kedai', 'resto', 'depot', 'alamat', 'jl', 'jalan', 'rt', 'rw', 'nomor', 'no.'];
            foreach ($regKeywords as $kw) {
                if (str_contains($text, $kw)) {
                    return true;
                }
            }
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
     * @param bool $isNewCustomer
     * @param string $customerName
     * @param string $infoStok
     * @return array|null
     */
    public function heuristicFallbackParser(string $chat, bool $isNewCustomer = false, string $customerName = 'Kak', string $infoStok = ''): ?array
    {
        // Guard Clause awal: Jika bukan format pesanan, return null
        if (!$this->isPotentialOrder($chat, $isNewCustomer)) {
            return null;
        }

        if ($isNewCustomer) {
            $lower = strtolower($chat);
            $hasStore = str_contains($lower, 'toko') || str_contains($lower, 'cafe') || str_contains($lower, 'kafe') || str_contains($lower, 'warung') || str_contains($lower, 'kedai') || str_contains($lower, 'resto');
            $hasAddress = str_contains($lower, 'jl') || str_contains($lower, 'jalan') || str_contains($lower, 'alamat');

            if ($hasStore && $hasAddress) {
                return [
                    'is_registration' => true,
                    'nama_toko'       => trim($chat),
                    'alamat'          => trim($chat),
                ];
            }

            return [
                'is_new_customer' => true,
                'reply'           => "Halo {$customerName}! Sebelum kami dapat memproses pesanan Anda, mohon kirimkan Nama Toko/Cafe dan Alamat Lengkap Anda terlebih dahulu ya.",
            ];
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

        // Validasi stok pada fallback parser
        if ($this->isStockExceeded($jumlah, $ukuran, $infoStok, $availableStock)) {
            return [
                'is_out_of_stock' => true,
                'reply'           => "Maaf {$customerName}, kebetulan stok es ukuran tersebut saat ini sedang kurang dari permintaan Kakak. Silakan kurangi jumlah pesanan, atau ketik *admin* agar kami dapat mengecek jadwal produksi selanjutnya.",
            ];
        }

        // 3. Deteksi Waktu Pengiriman Sederhana (Fallback)
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
                if (str_contains($text, 'besok')) {
                    $targetDate->addDay();
                }
                $targetDate->setTime($hour, $minute, 0);
                $waktuPengiriman = $targetDate->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {}
        }

        return [
            'jumlah_ball'      => max(1, $jumlah),
            'ukuran_produk'    => $ukuran,
            'waktu_pengiriman' => $waktuPengiriman,
        ];
    }
}
