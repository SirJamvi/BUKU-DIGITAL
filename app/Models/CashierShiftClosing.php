<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierShiftClosing extends Model
{
    use HasFactory, BelongsToBusiness, BelongsToBranch;

    protected $table = 'cashier_shift_closings';

    protected $fillable = [
        'business_id',
        'branch_id',
        'user_id',
        'date',
        'shift_code',
        'opening_cash',
        'cash_sales',
        'cash_debt_collection',
        'cash_expenses',
        'cash_handover_amount',
        'cash_refunds',
        'expected_drawer_cash',
        'actual_cash_counted',
        'variance',
        'ending_cash_for_next_shift',
        'notes',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'date'                       => 'date',
        'closed_at'                  => 'datetime',
        'opening_cash'               => 'float',
        'cash_sales'                 => 'float',
        'cash_debt_collection'       => 'float',
        'cash_expenses'              => 'float',
        'cash_handover_amount'       => 'float',
        'cash_refunds'               => 'float',
        'expected_drawer_cash'       => 'float',
        'actual_cash_counted'        => 'float',
        'variance'                   => 'float',
        'ending_cash_for_next_shift' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }
}
