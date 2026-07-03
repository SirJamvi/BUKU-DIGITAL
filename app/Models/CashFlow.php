<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness;
use Illuminate\Support\Str;

class CashFlow extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'cash_flow';

    protected $fillable = [
        'business_id',
        'type',
        'category_id',
        'payment_method',
        'amount',
        'description',
        'date',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * [BARU] Mutator Otomatis untuk payment_method
     */
    public function setPaymentMethodAttribute($value)
    {
        $this->attributes['payment_method'] = Str::slug($value);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
