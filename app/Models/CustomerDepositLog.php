<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;

class CustomerDepositLog extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'customer_deposit_logs';

    protected $fillable = [
        'business_id',
        'branch_id',
        'customer_id',
        'transaction_id',
        'cash_flow_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'payment_method',
        'notes',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'reversed_by_log_id',
        'reverses_log_id',
        'created_by',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'reversed_at'    => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScopes();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withoutGlobalScopes();
    }

    public function cashFlow(): BelongsTo
    {
        return $this->belongsTo(CashFlow::class)->withoutGlobalScopes();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversedByLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_log_id');
    }

    public function reversesLog(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_log_id');
    }

    public function isReversed(): bool
    {
        return !empty($this->reversed_at) || !empty($this->reversed_by_log_id);
    }

    public function isReversibleCredit(): bool
    {
        return $this->type === 'credit' && !$this->isReversed();
    }
}
