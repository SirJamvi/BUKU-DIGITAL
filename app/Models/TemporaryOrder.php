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
        'last_chat',
        'transaction_id',
    ];

    protected $casts = [
        'jumlah_ball'   => 'integer',
        'delivery_time' => 'datetime',
        'harga_asumsi'  => 'decimal:2',
        'total_harga'   => 'decimal:2',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

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
