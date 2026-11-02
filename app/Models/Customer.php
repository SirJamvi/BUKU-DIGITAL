<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class Customer extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'address',
        'join_date',
        'total_purchases',
        'loyalty_points',
        'status',
        'created_by',
    ];

    protected $casts = [
        'join_date' => 'date',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Menghitung total utang/kasbon pelanggan yang masih pending.
     */
    public function getTotalKasbon()
    {
        return $this->transactions()
                    ->where('type', 'sale')
                    ->where('payment_status', 'pending')
                    ->sum('total_amount');
    }
}