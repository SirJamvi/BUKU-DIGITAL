<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class CashFlow extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'cash_flow';

    protected $fillable = [
        'business_id',
        'type',
        'category_id',
        'amount',
        'description',
        'date',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}