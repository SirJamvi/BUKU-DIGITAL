<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness;

class CashierOpeningBalance extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'cashier_opening_balances';

    protected $fillable = [
        'business_id',
        'user_id',
        'date',
        'amount',
        'notes',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
