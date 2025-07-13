<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class ExpenseCategory extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'expense_categories';

    protected $fillable = [
        'business_id',
        'name',
        'type',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}