<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AiOrderDraft extends Model
{
    use HasFactory;

    protected $table = 'ai_order_drafts';

    protected $fillable = [
        'sender_phone',
        'data',
        'awaiting',
        'last_message_at',
        'expires_at',
    ];

    protected $casts = [
        'data'            => 'array',
        'last_message_at' => 'datetime',
        'expires_at'      => 'datetime',
    ];

    /**
     * Ambil atau buat instance draft untuk nomor pengirim tertentu.
     * Jika draft sudah kadaluarsa (30 menit), data dibersihkan kembali.
     */
    public static function getOrCreateDraft(string $senderPhone): self
    {
        $draft = self::firstOrNew(['sender_phone' => $senderPhone]);

        if (!$draft->exists) {
            $draft->data = [
                'nama_toko'        => null,
                'alamat'           => null,
                'lat'              => null,
                'lng'              => null,
                'jumlah_ball'      => null,
                'ukuran_produk'    => null,
                'waktu_pengiriman' => null,
                'is_registration'  => false,
            ];
            $draft->awaiting = null;
            $draft->last_message_at = now();
            $draft->expires_at = now()->addMinutes(30);
            $draft->save();
            return $draft;
        }

        // Cek kadaluarsa
        if ($draft->expires_at && $draft->expires_at->isPast()) {
            $draft->data = [
                'nama_toko'        => null,
                'alamat'           => null,
                'lat'              => null,
                'lng'              => null,
                'jumlah_ball'      => null,
                'ukuran_produk'    => null,
                'waktu_pengiriman' => null,
                'is_registration'  => false,
            ];
            $draft->awaiting = null;
        }

        $draft->last_message_at = now();
        $draft->expires_at = now()->addMinutes(30);
        $draft->save();

        return $draft;
    }

    /**
     * Gabungkan data baru ke draft saat ini.
     * Aturan keras: Slot yang sudah terisi TIDAK BOLEH ditimpa dengan nilai null / kosong!
     */
    public function mergeData(array $newData): self
    {
        $current = $this->data ?? [];

        foreach ($newData as $key => $val) {
            if ($val !== null && $val !== '') {
                // Jangan timpa ukuran yang valid jika nilai baru tidak valid
                if ($key === 'ukuran_produk') {
                    $valClean = strtolower(trim((string) $val));
                    if (in_array($valClean, ['20kg', '15kg', '10kg'])) {
                        $current[$key] = $valClean;
                    }
                    continue;
                }

                $current[$key] = $val;
            }
        }

        $this->data = $current;
        $this->last_message_at = now();
        $this->expires_at = now()->addMinutes(30);
        $this->save();

        return $this;
    }

    /**
     * Reset draft setelah pesanan berhasil masuk / dibatalkan.
     */
    public function clearDraft(): self
    {
        $this->data = [
            'nama_toko'        => null,
            'alamat'           => null,
            'lat'              => null,
            'lng'              => null,
            'jumlah_ball'      => null,
            'ukuran_produk'    => null,
            'waktu_pengiriman' => null,
            'is_registration'  => false,
        ];
        $this->awaiting = null;
        $this->last_message_at = now();
        $this->expires_at = now()->addMinutes(30);
        $this->save();

        return $this;
    }

    /**
     * Set slot yang sedang ditunggu (awaiting).
     */
    public function setAwaiting(?string $slot): self
    {
        $this->awaiting = $slot;
        $this->save();

        return $this;
    }

    /**
     * Cek apakah slot kuantitas sudah terpenuhi.
     */
    public function hasQty(): bool
    {
        return !empty($this->data['jumlah_ball']) && (float) $this->data['jumlah_ball'] > 0;
    }

    /**
     * Cek apakah slot ukuran sudah terpenuhi (hanya 20kg, 15kg, 10kg).
     */
    public function hasUkuran(): bool
    {
        return !empty($this->data['ukuran_produk']) && in_array($this->data['ukuran_produk'], ['20kg', '15kg', '10kg']);
    }

    /**
     * Cek apakah data nama toko dan alamat (atau lokasi) sudah terpenuhi.
     */
    public function hasNameAndAddress(): bool
    {
        $hasName = !empty($this->data['nama_toko']);
        $hasAddress = !empty($this->data['alamat']) || (!empty($this->data['lat']) && !empty($this->data['lng']));

        return $hasName && $hasAddress;
    }
}

