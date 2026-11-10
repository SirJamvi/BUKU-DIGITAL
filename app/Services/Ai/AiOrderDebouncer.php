<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiOrderDebouncer
{
    /**
     * Cek apakah pesan merupakan kata kunci human handoff (admin).
     * Syarat: kata utuh "admin" dan panjang pesan pendek (< 25 karakter).
     */
    public static function isHumanHandoff(string $message): bool
    {
        $clean = strtolower(trim($message));
        if (strlen($clean) >= 25) {
            return false;
        }

        return (bool) preg_match('/\badmin\b/i', $clean);
    }

    /**
     * Cek apakah pesan merupakan kata kunci cepat pembatalan atau perubahan.
     */
    public static function isCancelOrChange(string $message): bool
    {
        $clean = strtolower(trim($message));
        if (strlen($clean) > 30) {
            return false;
        }

        return (bool) preg_match('/^(?:batal|cancel|gak jadi|gajadi|ubah|ganti)\b/i', $clean);
    }

    /**
     * Cek apakah pesan merupakan kata persetujuan/terima kasih (ACK).
     */
    public static function isAckMessage(string $message): bool
    {
        $clean = strtolower(trim($message));
        if (strlen($clean) > 40) {
            return false;
        }

        $ackWords = 'oke|ok|siap|iya|ya|sip|makasih|makasi|terima\s*kasih|terimakasih|thanks|thx';
        $honorifics = 'kak|pak|min|admin|ya|mas|bang|gan';
        return (bool) preg_match("/^(?:(?:{$ackWords})\s*)+(?:(?:{$honorifics})\s*)*[\.!\s]*$/i", $clean);
    }

    /**
     * Tentukan durasi debounce dalam detik.
     * Pesan pembatalan/perubahan dan pesan ACK diberi durasi singkat (default 2 detik).
     */
    public static function getDebounceSeconds(string $message): int
    {
        if (self::isCancelOrChange($message) || self::isAckMessage($message)) {
            return (int) config('ai_order.debounce_short_seconds', 2);
        }

        return (int) config('ai_order.debounce_seconds', 7);
    }

    /**
     * Tambahkan pesan ke buffer antrean per pengirim dan perbarui token versi secara atomik.
     * Menggunakan Cache::lock('ai_buf_{pengirim}', 5)->block(3) untuk mencegah lost update.
     *
     * @return array [string $token, int $debounceSeconds, int $bubblesIn]
     */
    public static function bufferMessage(string $sender, string $message, array $payload): array
    {
        $bufferKey  = 'debounce_buffer_' . $sender;
        $tokenKey   = 'debounce_token_' . $sender;
        $counterKey = 'debounce_counter_' . $sender;
        $lockKey    = 'ai_buf_' . $sender;

        $lock = Cache::lock($lockKey, 5);
        $lock->block(3);

        try {
            $token = microtime(true) . '_' . Str::random(8);
            $debounceSeconds = self::getDebounceSeconds($message);

            // Ambil buffer yang ada dan tambahkan item baru secara atomik
            $buffer = Cache::get($bufferKey, []);
            $buffer[] = [
                'message'   => $message,
                'payload'   => $payload,
                'timestamp' => microtime(true),
            ];

            $inCount = (int) Cache::get($counterKey, 0) + 1;

            // Simpan buffer, token versi terbaru, dan hitungan masuk secara atomik
            Cache::put($bufferKey, $buffer, now()->addMinutes(2));
            Cache::put($tokenKey, $token, now()->addMinutes(2));
            Cache::put($counterKey, $inCount, now()->addMinutes(2));

            return [$token, $debounceSeconds, $inCount];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Cek apakah token saat ini masih merupakan token paling baru untuk pengirim.
     */
    public static function isLatestToken(string $sender, string $token): bool
    {
        $currentToken = Cache::get('debounce_token_' . $sender);
        return $currentToken === $token;
    }

    /**
     * Cek apakah saat ini masih ada bubble lain yang belum diproses di buffer antrean pengirim.
     */
    public static function hasPendingBuffer(string $sender): bool
    {
        $bufferKey = 'debounce_buffer_' . $sender;
        $buffer = Cache::get($bufferKey, []);
        return !empty($buffer);
    }

    /**
     * Ambil seluruh isi buffer dan kosongkan dari cache dalam satu atomic lock.
     *
     * @return array ['items' => array, 'bubbles_in' => int, 'bubbles_processed' => int]
     */
    public static function flushBuffer(string $sender): array
    {
        $bufferKey  = 'debounce_buffer_' . $sender;
        $tokenKey   = 'debounce_token_' . $sender;
        $counterKey = 'debounce_counter_' . $sender;
        $lockKey    = 'ai_buf_' . $sender;

        $lock = Cache::lock($lockKey, 5);
        $lock->block(3);

        try {
            $buffer = Cache::get($bufferKey, []);
            $totalIn = (int) Cache::get($counterKey, count($buffer));

            Cache::forget($bufferKey);
            Cache::forget($tokenKey);
            Cache::forget($counterKey);

            return [
                'items'             => $buffer,
                'bubbles_in'        => $totalIn,
                'bubbles_processed' => count($buffer),
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Gabungkan semua teks dari buffer menjadi satu teks utuh, dan ekstrak lokasi bila ada.
     *
     * @param array $bufferItems
     * @return array [string $combinedMessage, ?string $location, array $lastPayload]
     */
    public static function combineBuffer(array $bufferItems): array
    {
        $texts = [];
        $location = null;
        $lastPayload = [];

        foreach ($bufferItems as $item) {
            $msg = trim($item['message'] ?? '');
            if (!empty($msg) && $msg !== 'non-text message' && $msg !== 'non-button message') {
                $texts[] = $msg;
            }

            if (!empty($item['payload']['location'])) {
                $location = (string) $item['payload']['location'];
            }

            $lastPayload = $item['payload'] ?? $lastPayload;
        }

        $combinedMessage = implode("\n", array_unique($texts));

        return [
            'combined_message' => $combinedMessage,
            'location'         => $location,
            'last_payload'     => $lastPayload,
        ];
    }
}

