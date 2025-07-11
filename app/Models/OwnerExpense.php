<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OwnerExpense extends Model
{
    use HasFactory;

    protected $table = 'owner_expenses';

    protected $fillable = [
        'type',
        'amount',
        'title',
        'description',
        'date',
        'category',
        'payment_method',
        'reference_number',
        'status',
        'fund_allocation_history_id',
        'receipt_file',
        'is_recurring',
        'recurring_type',
        'recurring_interval',
        'recurring_end_date',
        'is_tax_deductible',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'recurring_end_date' => 'date',
        'is_tax_deductible' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function fundAllocationHistory()
    {
        return $this->belongsTo(FundAllocationHistory::class, 'fund_allocation_history_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}