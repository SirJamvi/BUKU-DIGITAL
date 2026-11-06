<?php

namespace App\Services\Ai;

class ReplyTemplates
{
    public const GREETING = "Halo Kak, saya asisten otomatis Adin Crystal. Langsung ketik aja, contoh: 3 ball yang 20 jam 4 sore\n(Ketik *admin* untuk bicara dengan staf)";

    public const NEED_NAME_ADDRESS = "Siap, {qty} ball {ukuran}. Tinggal nama toko & alamat (atau share lokasi) ya.";

    public const NEED_QTY = "Mau berapa ball? Ukuran 20 / 15 / 10 kg?";

    public const NEED_UKURAN = "Ukuran berapa Kak? 20 / 15 / 10 kg?";

    public const UNKNOWN_UKURAN = "Ukuran yang tersedia 20 / 15 / 10 kg. Mau yang mana Kak?";

    public const ORDER_IN_EXISTING = "✅ Pesanan masuk: {qty} ball {ukuran} -> {nama_toko}{jam}.\nSalah? Balas *BATAL* atau *UBAH*.";

    public const ORDER_IN_NEW = "✅ Terdaftar & pesanan masuk: {qty} ball {ukuran} -> {nama_toko}, {alamat}.\nSalah? Balas *BATAL* atau *UBAH*.";

    public const ORDER_IN_KASBON = "Ada kasbon Rp {nominal}, titip ke supir saat pengiriman ya.";

    public const OUT_OF_STOCK = "Maaf Kak, stok ukuran itu belum cukup untuk jumlah segitu. Kurangi jumlahnya atau ketik *admin*.";

    public const CANCELLED = "Pesanan dibatalkan. Mau pesan lagi? Langsung ketik aja.";

    public const ALREADY_PROCESSED = "Pesanan sudah diproses kasir. Ketik *admin* untuk perubahan.";

    public const LOCATION_ONLY = "Lokasi diterima 📍 Mau pesan berapa ball & ukuran berapa?";

    public const UNSUPPORTED_MEDIA = "Maaf, saya hanya bisa baca teks dan lokasi.";

    public const UBAH_PROMPT = "Oke, ketik perubahannya aja, contoh: jadi 3 ball.";

    /**
     * Render template teks dengan penggantian parameter yang aman dan rapi.
     */
    public static function render(string $key, array $params = []): string
    {
        $template = match ($key) {
            'greeting'          => self::GREETING,
            'need_name_address' => self::NEED_NAME_ADDRESS,
            'need_qty'          => self::NEED_QTY,
            'need_ukuran'       => self::NEED_UKURAN,
            'unknown_ukuran'    => self::UNKNOWN_UKURAN,
            'order_in_existing' => self::ORDER_IN_EXISTING,
            'order_in_new'      => self::ORDER_IN_NEW,
            'order_in_kasbon'   => self::ORDER_IN_KASBON,
            'out_of_stock'      => self::OUT_OF_STOCK,
            'cancelled'         => self::CANCELLED,
            'already_processed' => self::ALREADY_PROCESSED,
            'location_only'     => self::LOCATION_ONLY,
            'unsupported_media' => self::UNSUPPORTED_MEDIA,
            'ubah_prompt'       => self::UBAH_PROMPT,
            default             => self::GREETING,
        };

        // Format khusus jam pengiriman untuk template order_in_existing
        if (isset($params['jam']) && !empty($params['jam'])) {
            $params['jam'] = ', jam ' . trim((string) $params['jam']);
        } else {
            $params['jam'] = '';
        }

        // Format nominal kasbon
        if (isset($params['nominal'])) {
            $params['nominal'] = number_format((float) $params['nominal'], 0, ',', '.');
        }

        foreach ($params as $k => $v) {
            $template = str_replace('{' . $k . '}', (string) $v, $template);
        }

        return trim($template);
    }

    /**
     * Helper untuk membuat balasan konfirmasi pesanan masuk (dengan kasbon jika ada).
     */
    public static function renderOrderIn(
        bool $isNewCustomer,
        int $qty,
        string $ukuran,
        string $namaToko,
        ?string $alamat = null,
        ?string $jam = null,
        float $kasbon = 0
    ): string {
        $base = $isNewCustomer
            ? self::render('order_in_new', [
                'qty'       => $qty,
                'ukuran'    => $ukuran,
                'nama_toko' => $namaToko,
                'alamat'    => $alamat ?? 'Lokasi Terdaftar',
            ])
            : self::render('order_in_existing', [
                'qty'       => $qty,
                'ukuran'    => $ukuran,
                'nama_toko' => $namaToko,
                'jam'       => $jam,
            ]);

        if ($kasbon > 0) {
            $kasbonText = self::render('order_in_kasbon', ['nominal' => $kasbon]);
            $base .= "\n" . $kasbonText;
        }

        return $base;
    }
}
