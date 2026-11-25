<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory, BelongsToBusiness, BelongsToBranch;

    protected $fillable = [
        'business_id',
        'branch_id',
        'type',
        'customer_id',
        'driver_id',
        'claimed_by_driver_id',
        'delivery_trip_code',
        'total_amount',
        'deposit_used',
        'net_payable_amount',
        'payment_method',
        'initial_payment_method',
        'settlement_payment_method',
        'settled_at',
        'payment_status',
        'delivery_status',
        'order_status',
        'status',
        'delivered_at',
        'transaction_date',
        'notes',
        'void_reason',
        'voided_by',
        'voided_at',
        'created_by',
        'payment_proof',
        'proof_verified_at',
        'proof_verified_by',
    ];

    protected $casts = [
        'total_amount'       => 'decimal:2',
        'deposit_used'       => 'decimal:2',
        'net_payable_amount' => 'decimal:2',
        'transaction_date'   => 'datetime',
        'delivered_at'       => 'datetime',
        'settled_at'         => 'datetime',
        'voided_at'          => 'datetime',
        'proof_verified_at'  => 'datetime',
    ];

    /**
     * Sisa tagihan efektif setelah dipotong deposit.
     */
    public function getEffectivePayableAmountAttribute(): float
    {
        if ($this->net_payable_amount !== null) {
            return (float) $this->net_payable_amount;
        }
        return (float) max(0, (float) $this->total_amount - (float) ($this->deposit_used ?? 0));
    }

    public function depositLogs()
    {
        return $this->hasMany(CustomerDepositLog::class);
    }

    /**
     * [BARU] Mutator Otomatis untuk payment_method
     * Mengubah spasi menjadi strip dan huruf kecil secara otomatis.
     */
    public function setPaymentMethodAttribute($value)
    {
        $this->attributes['payment_method'] = Str::slug($value);
    }


    // Relasi ke kurir/driver (mendukung driver_id maupun claimed_by_driver_id)
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Cabang tempat transaksi ini tercatat.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Format nomor nota/invoice standar per cabang: TRX-{branch.code}-{id 6 digit}
     * Contoh: TRX-CB-PUSAT-000123
     */
    public function getReceiptNumberAttribute(): string
    {
        $code = $this->branch ? $this->branch->code : 'CB';
        return sprintf('TRX-%s-%06d', $code, $this->id);
    }

    public function receiptNumber(): string
    {
        return $this->getReceiptNumberAttribute();
    }

    /**
     * The business that this transaction belongs to.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function proofVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_verified_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Izinkan penemuan model transaksi lintas cabang dalam 1 tenant bisnis pada route model binding.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->withoutGlobalScope('branch')->where($field ?? $this->getRouteKeyName(), $value);
    }
}