<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundAllocationHistory extends Model
{
    use HasFactory;

    protected $table = 'fund_allocation_history';

    protected $fillable = [
        'period_month',
        'period_year',
        'net_profit',
        'allocated_amount',
        'allocation_category',
        'allocation_percentage',
        'allocation_name',
        'is_manual',
        'notes',
        'status',
        'owner_profit_id',
        'fund_allocation_setting_id',
        'created_by',
        'allocated_at',
        'used_at',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'allocated_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function ownerProfit()
    {
        return $this->belongsTo(OwnerProfit::class, 'owner_profit_id');
    }

    public function fundAllocationSetting()
    {
        return $this->belongsTo(FundAllocationSetting::class, 'fund_allocation_setting_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}