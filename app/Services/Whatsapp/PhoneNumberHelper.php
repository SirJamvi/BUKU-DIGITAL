<?php

namespace App\Services\Whatsapp;

class PhoneNumberHelper
{
    /**
     * Normalisasi nomor telepon ke format kanonik internasional tanpa tanda plus (contoh: 6281234567890).
     */
    public static function normalize(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);

        if (empty($clean)) {
            return '';
        }

        // 08xxxx -> 628xxxx
        if (str_starts_with($clean, '08')) {
            return '62' . substr($clean, 1);
        }

        // 8xxxx -> 628xxxx
        if (str_starts_with($clean, '8') && strlen($clean) >= 9 && strlen($clean) <= 13) {
            return '62' . $clean;
        }

        return $clean;
    }

    /**
     * Konversi nomor kanonik ke format lokal Indonesia (contoh: 081234567890).
     */
    public static function toLocal(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if (empty($normalized)) {
            return '';
        }

        if (str_starts_with($normalized, '62')) {
            return '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Menghasilkan variasi format nomor untuk pencarian database yang komprehensif.
     *
     * @return array<string>
     */
    public static function variations(?string $phone): array
    {
        if (empty($phone)) {
            return [];
        }

        $raw = trim($phone);
        $clean = preg_replace('/[^0-9]/', '', $phone);
        $normalized = self::normalize($phone);
        $local = self::toLocal($phone);

        $variations = array_filter([
            $raw,
            $clean,
            $normalized,
            $local,
            !empty($normalized) ? '+' . $normalized : null,
            !empty($clean) ? '+' . $clean : null,
        ]);

        return array_values(array_unique($variations));
    }

    /**
     * Masker 4 digit tengah nomor telepon untuk log aman (contoh: 0812****7890 atau 6281****7890).
     */
    public static function mask(?string $phone): string
    {
        if (empty($phone)) {
            return '-';
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($clean);

        if ($len < 8) {
            return substr($clean, 0, 2) . '****' . substr($clean, -2);
        }

        $startLen = 4;
        $endLen = 4;
        $middleLen = max(2, $len - $startLen - $endLen);

        $prefix = substr($clean, 0, $startLen);
        $suffix = substr($clean, -$endLen);

        return $prefix . str_repeat('*', min(4, $middleLen)) . $suffix;
    }
}
