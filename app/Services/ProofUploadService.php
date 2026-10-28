<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProofUploadService
{
    /**
     * Upload bukti nota pengeluaran (Expenses).
     */
    public static function uploadReceipt(UploadedFile $file): string
    {
        return self::processUpload($file, 'uploads/receipts', 'receipt');
    }

    /**
     * Upload screenshot bukti pembayaran non-cash / WhatsApp (Pending Payment).
     */
    public static function uploadPaymentProof(UploadedFile $file): string
    {
        return self::processUpload($file, 'uploads/payments', 'proof');
    }

    /**
     * Memproses file upload dengan kompresi otomatis (jika ekstensi gambar mendukung),
     * atau fallback ke penyimpanan standar jika kompresi gagal/tidak didukung.
     */
    protected static function processUpload(UploadedFile $file, string $folder, string $prefix): string
    {
        $targetDir = public_path($folder);

        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'bmp']);

        // 1. Coba kompresi dengan Intervention Image v3 jika ekstensi gambar didukung
        if ($isImage && class_exists('\Intervention\Image\ImageManager') && class_exists('\Intervention\Image\Drivers\Gd\Driver')) {
            try {
                $driver = new \Intervention\Image\Drivers\Gd\Driver();
                $manager = new \Intervention\Image\ImageManager($driver);
                $image = $manager->read($file->getRealPath());

                // Scale down jika lebar melebihi 1200px agar hemat memori & storage
                if ($image->width() > 1200) {
                    $image->scaleDown(width: 1200);
                }

                $filename = $prefix . '_' . date('Ymd_His') . '_' . Str::random(8) . '.webp';
                $fullPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

                $image->toWebp(80)->save($fullPath);

                return $folder . '/' . $filename;
            } catch (\Throwable $e) {
                // Jangan gagalkan transaksi jika kompresi error, fallback langsung ke penyimpanan standar
            }
        }

        // 2. Fallback Standar: Simpan file asli tanpa kompresi
        $filename = $prefix . '_' . date('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
        $file->move($targetDir, $filename);

        return $folder . '/' . $filename;
    }

    /**
     * Hapus file bukti jika diperlukan.
     */
    public static function deleteProof(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $fullPath = public_path($relativePath);
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
