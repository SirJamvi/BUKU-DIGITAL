<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerProfit extends Model
{
    use HasFactory;

    protected $table = 'owner_profits';

    protected $fillable = [
        'period_month',
        'period_year',
        'net_profit',
        'owner_salary',
        'withdrawal_amount',
        'reinvestment',
        'allocated_funds',
        'allocation_settings',
        'auto_allocated',
        'manual_override',
        'notes',
        'status',
        'allocated_at',
    ];

    protected $casts = [
        'allocation_settings' => 'json',
        'auto_allocated' => 'boolean',
        'manual_override' => 'boolean',
        'allocated_at' => 'datetime',
    ];

    public function fundAllocationHistories()
    {
        return $this->hasMany(FundAllocationHistory::class, 'owner_profit_id');
    }
}