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
     * Tentukan durasi debounce dalam detik.
     * Pesan pembatalan/perubahan diberi durasi singkat (2 detik) agar responsif.
     */
    public static function getDebounceSeconds(string $message): int
    {
        if (self::isCancelOrChange($message)) {
            return 2;
        }

        return (int) config('services.ai_order.debounce_seconds', env('AI_ORDER_DEBOUNCE_SECONDS', 7));
    }

    /**
     * Tambahkan pesan ke buffer antrean per pengirim dan perbarui token versi.
     *
     * @return array [string $token, int $debounceSeconds]
     */
    public static function bufferMessage(string $sender, string $message, array $payload): array
    {
        $bufferKey = 'debounce_buffer_' . $sender;
        $tokenKey  = 'debounce_token_' . $sender;

        $token = microtime(true) . '_' . Str::random(8);
        $debounceSeconds = self::getDebounceSeconds($message);

        // Ambil buffer yang ada dan tambahkan item baru
        $buffer = Cache::get($bufferKey, []);
        $buffer[] = [
            'message'   => $message,
            'payload'   => $payload,
            'timestamp' => microtime(true),
        ];

        // Simpan buffer (berlaku 2 menit)
        Cache::put($bufferKey, $buffer, now()->addMinutes(2));
        Cache::put($tokenKey, $token, now()->addMinutes(2));

        return [$token, $debounceSeconds];
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
     * Ambil seluruh isi buffer dan kosongkan dari cache.
     *
     * @return array
     */
    public static function flushBuffer(string $sender): array
    {
        $bufferKey = 'debounce_buffer_' . $sender;
        $buffer = Cache::get($bufferKey, []);

        Cache::forget($bufferKey);
        Cache::forget('debounce_token_' . $sender);

        return $buffer;
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
