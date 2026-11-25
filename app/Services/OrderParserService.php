<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service untuk mem-parsing pesan WhatsApp menggunakan Jev AI (TypeSafe AI - System One).
 * Memanfaatkan primitive deterministik (Noul, Choice, Score) untuk evaluasi cepat dan terstruktur.
 */
class OrderParserService
{
    /**
     * Timeout pemanggilan HTTP ke Jev AI (dalam detik).
     */
    protected int $timeoutSeconds = 8;

    /**
     * Parse pesan WhatsApp pelanggan menggunakan Jev AI primitives.
     *
     * @param string $message Pesan teks alami dari pelanggan WhatsApp
     * @param int|null $businessId ID Bisnis untuk pemetaan produk lokal
     * @return array{
     *     is_order: bool,
     *     noul_score: float,
     *     product: string,
     *     product_name: string,
     *     product_id: int|null,
     *     qty: int|float,
     *     confidence: float,
     *     raw_answers?: array,
     *     error?: string
     * }
     */
    public function parse(string $message, ?int $businessId = null): array
    {
        $cleanMessage = trim($message);

        if (empty($cleanMessage)) {
            return [
                'is_order'     => false,
                'noul_score'   => 0.0,
                'product'      => 'other',
                'product_name' => 'Unknown',
                'product_id'   => null,
                'qty'          => 1,
                'confidence'   => 0.0,
            ];
        }

        try {
            return $this->callJevAi($cleanMessage, $businessId);
        } catch (Throwable $e) {
            Log::error('OrderParserService: Jev AI API Error - ' . $e->getMessage(), [
                'message' => $cleanMessage,
                'trace'   => $e->getTraceAsString(),
            ]);

            // Graceful error response saat network atau kredensial API gagal
            return [
                'is_order'     => false,
                'noul_score'   => 0.0,
                'product'      => 'other',
                'product_name' => 'Unknown',
                'product_id'   => null,
                'qty'          => 1,
                'confidence'   => 0.0,
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Panggilan ke endpoint System One Jev AI (TypeSafe AI).
     */
    protected function callJevAi(string $message, ?int $businessId = null): array
    {
        $apiKey = config('services.jev.api_key', env('JEV_API_KEY'));
        $baseUrl = config('services.jev.base_url', env('JEV_API_URL', 'https://api.typesafe.ai/v1'));
        $model = config('services.jev.model', env('JEV_MODEL', 'jev-latest'));
        $confidenceThreshold = (float) config('services.jev.confidence_threshold', env('JEV_CONFIDENCE_THRESHOLD', 0.70));

        // Jika API Key belum diset, log peringatan & kembalikan respons aman
        if (empty($apiKey)) {
            Log::warning('OrderParserService: JEV_API_KEY belum dikonfigurasi di environment (.env).');
            return [
                'is_order'     => false,
                'noul_score'   => 0.0,
                'product'      => 'other',
                'product_name' => 'Unknown',
                'product_id'   => null,
                'qty'          => 1,
                'confidence'   => 0.0,
                'error'        => 'Jev AI API key is not configured.',
            ];
        }

        // Endpoint resmi Jev AI System One
        $endpoint = rtrim($baseUrl, '/') . '/systemone';

        /**
         * Struktur request Jev AI dengan 3 tipe primitive:
         * 1. Noul: Validasi Yes/No apakah pesan merupakan niat memesan es kristal.
         * 2. Choice: Klasifikasi produk ke 'es_kristal_10kg', 'es_kristal_20kg', 'es_balok', 'other'.
         * 3. Choice/Score: Ekstraksi kuantitas pasti yang dipesan.
         */
        $payload = [
            'model'     => $model,
            'state'     => $message,
            'questions' => [
                // a. Noul (Boolean probability): Apakah pesan ini merupakan niat pesanan es kristal yang valid?
                'is_order' => [
                    'type'         => 'noul',
                    'instructions' => 'Is this message a valid customer order intent for ice crystals?',
                ],

                // b. Choice: Klasifikasikan produk ke salah satu opsi yang ditentukan
                'product' => [
                    'type'         => 'choice',
                    'instructions' => 'Classify the ice crystal product being ordered.',
                    'criteria'     => [
                        'es_kristal_10kg' => 'Ice crystal in 10kg package, ball, or bag (e.g., es kristal 10kg, 10 kilo)',
                        'es_kristal_20kg' => 'Ice crystal in 20kg package, ball, or bag (e.g., es kristal 20kg, 20 kilo)',
                        'es_balok'        => 'Block ice / es balok / es batu balok',
                        'other'           => 'Other item, general ice crystal without weight specified, or non-product query',
                    ],
                ],

                // c. Choice: Ekstraksi kuantitas pasti yang dipesan
                'qty' => [
                    'type'         => 'choice',
                    'instructions' => 'Extract the exact numeric quantity (number of units, balls, bags, or packs) being ordered.',
                    'criteria'     => [
                        '1'     => '1 unit, 1 ball, 1 karung, 1 sak, 1 kantong, 1 biji',
                        '2'     => '2 units, 2 balls, 2 karung, 2 sak, 2 kantong',
                        '3'     => '3 units, 3 balls, 3 karung, 3 sak, 3 kantong',
                        '4'     => '4 units, 4 balls, 4 karung, 4 sak, 4 kantong',
                        '5'     => '5 units, 5 balls, 5 karung, 5 sak, 5 kantong',
                        '10'    => '10 units, 10 balls, 10 karung, 10 sak',
                        'other' => 'Other quantity greater than 10 or unspecified quantity',
                    ],
                ],
            ],
        ];

        // Eksekusi HTTP Request ke Jev AI
        $response = Http::timeout($this->timeoutSeconds)
            ->withToken($apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("Jev AI HTTP Error {$response->status()}: " . $response->body());
        }

        $responseData = $response->json();
        $answers = $responseData['answers'] ?? [];

        // 1. Ekstraksi hasil 'Noul' (Boolean + Probability)
        // Format jawaban Jev AI: $answers['is_order']['noul'] berisi float 0.0 - 1.0
        $noulScore = (float) ($answers['is_order']['noul'] ?? 0.0);
        $isOrder = ($noulScore >= $confidenceThreshold);

        // 2. Ekstraksi hasil 'Choice' (Klasifikasi Produk)
        // Format jawaban Jev AI: $answers['product']['choice'] berisi string kunci opsi
        $productKey = (string) ($answers['product']['choice'] ?? 'other');
        $choiceConfidence = (float) ($answers['product']['confidence'] ?? 0.0);

        // Validasi agar pilihan produk strictly sesuai requirement
        $validProducts = ['es_kristal_10kg', 'es_kristal_20kg', 'es_balok', 'other'];
        if (!in_array($productKey, $validProducts, true)) {
            $productKey = 'other';
        }

        // 3. Ekstraksi hasil 'Score / Choice' (Kuantitas)
        $rawQtyChoice = (string) ($answers['qty']['choice'] ?? '1');
        $qty = 1;

        if (is_numeric($rawQtyChoice)) {
            $qty = (int) $rawQtyChoice;
        } elseif ($rawQtyChoice === 'other') {
            // Jika Jev AI memilih 'other' (misal pesanan dalam jumlah besar > 10),
            // lakukan ekstraksi angka numerik langsung dari teks pesan
            if (preg_match('/\b(\d+)\s*(?:ball|bal|sak|karung|kantong|kg|biji)?\b/i', $message, $qtyMatches)) {
                $qty = (int) $qtyMatches[1];
            }
        }

        // Map nama produk ramah katalog POS
        $humanProductName = match ($productKey) {
            'es_kristal_10kg' => 'Es Kristal 10kg',
            'es_kristal_20kg' => 'Es Kristal 20kg',
            'es_balok'        => 'Es Balok',
            default           => 'Es Kristal',
        };

        // Cari ID produk di database lokal bila tersedia
        $productId = $this->resolveLocalProductId($humanProductName, $businessId);

        return [
            'is_order'     => $isOrder,
            'noul_score'   => $noulScore,
            'product'      => $productKey,
            'product_name' => $humanProductName,
            'product_id'   => $productId,
            'qty'          => max(1, $qty),
            'confidence'   => $choiceConfidence,
            'raw_answers'  => $answers,
        ];
    }

    /**
     * Mencocokkan nama produk hasil Jev AI dengan tabel products di MySQL.
     */
    protected function resolveLocalProductId(string $productName, ?int $businessId = null): ?int
    {
        try {
            $query = Product::where('is_active', true);
            if ($businessId) {
                $query->where('business_id', $businessId);
            }

            // Coba exact match terlebih dahulu
            $product = (clone $query)->where('name', 'LIKE', "%{$productName}%")->first();
            if ($product) {
                return (int) $product->id;
            }

            // Fallback produk es kristal pertama
            $fallbackProduct = (clone $query)->where('name', 'LIKE', '%Es Kristal%')->first();
            return $fallbackProduct ? (int) $fallbackProduct->id : null;
        } catch (Throwable $e) {
            Log::warning('OrderParserService: Gagal mencocokkan product_id lokal - ' . $e->getMessage());
            return null;
        }
    }
}
