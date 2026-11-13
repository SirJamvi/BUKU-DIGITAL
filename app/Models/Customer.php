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

    public function phones()
    {
        return $this->hasMany(CustomerPhone::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Menghitung total utang/kasbon pelanggan yang masih pending.
     * Jika $branchId diberikan (atau konteks cabang aktif), dihitung terisolasi per cabang.
     */
    public function getTotalKasbon(?int $branchId = null)
    {
        $branchId = $branchId ?? app(\App\Support\BranchContext::class)->getBranchId();

        $query = $this->transactions()
                    ->where('type', 'sale')
                    ->where('payment_status', 'pending');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->sum('total_amount');
    }
}