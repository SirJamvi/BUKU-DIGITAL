<?php

namespace App\Models;

use App\Traits\BelongsToBranch;
use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMonthlyClosing extends Model
{
    use HasFactory, BelongsToBusiness, BelongsToBranch;

    protected $table = 'branch_monthly_closings';

    protected $fillable = [
        'business_id',
        'branch_id',
        'period_month',
        'period_year',
        'monthly_income',
        'monthly_cogs',
        'gross_profit',
        'monthly_expense',
        'net_profit',
        'status',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected $casts = [
        'closed_at'       => 'datetime',
        'period_month'    => 'integer',
        'period_year'     => 'integer',
        'monthly_income'  => 'float',
        'monthly_cogs'    => 'float',
        'gross_profit'    => 'float',
        'monthly_expense' => 'float',
        'net_profit'      => 'float',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

