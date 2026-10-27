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
        'is_cogs',
        'payment_method',
        'funding_source',
        'affects_drawer',
        'amount',
        'description',
        'date',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'affects_drawer' => 'boolean',
        'is_cogs' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($cashFlow) {
            if ($cashFlow->is_cogs === null && $cashFlow->category_id) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('cash_flow', 'is_cogs')) {
                    $category = ExpenseCategory::find($cashFlow->category_id);
                    if ($category) {
                        $cashFlow->is_cogs = (bool) $category->is_cogs;
                    }
                }
            }
        });
    }

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

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'reference_id');
    }
}
