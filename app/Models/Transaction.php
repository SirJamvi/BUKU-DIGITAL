<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'type',
        'customer_id',
        'total_amount',
        'payment_method',
        'payment_status',
        'status',
        'transaction_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

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
}
