<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryOrder extends Model
{
    use HasFactory;

    protected $table = 'temporary_orders';

    protected $fillable = [
        'customer_id',
        'sender_phone',
        'jumlah_ball',
        'ukuran_produk',
        'delivery_time',
        'harga_asumsi',
        'total_harga',
        'status',
        'is_new_customer',
        'last_chat',
        'transaction_id',
    ];

    protected $casts = [
        'jumlah_ball'     => 'float',
        'is_new_customer' => 'boolean',
        'delivery_time'   => 'datetime',
        'harga_asumsi'    => 'decimal:2',
        'total_harga'     => 'decimal:2',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Lazy-expiry: Menandai pesanan berstatus 'validated' yang lebih lama dari 24 jam menjadi 'expired'.
     */
    public static function lazyExpireOldOrders(): int
    {
        return self::where('status', 'validated')
            ->where('created_at', '<', now()->subHours(24))
            ->update(['status' => 'expired']);
    }

    /**
     * Relasi ke Pelanggan di tabel customers
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relasi ke Transaksi POS setelah pesanan divalidasi
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
