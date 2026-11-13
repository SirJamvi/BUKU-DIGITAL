<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 
use App\Traits\BelongsToBranch;

class Inventory extends Model
{
    use HasFactory, BelongsToBusiness, BelongsToBranch;

    protected $table = 'inventory';

    protected $fillable = [
        'business_id',
        'branch_id',
        'product_id',
        'current_stock',
        'cost_price',
        'min_stock',
        'critical_stock',
        'max_stock',
        'last_updated',
        'updated_by',
    ];

    public function getCriticalStockAttribute($value): int
    {
        return $value !== null ? (int) $value : 5;
    }

    protected $casts = [
        'cost_price'   => 'float',
        'last_updated' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
