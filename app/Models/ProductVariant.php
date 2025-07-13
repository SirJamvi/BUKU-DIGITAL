<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class ProductVariant extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'product_variants';

    protected $fillable = [
        'business_id',
        'product_id',
        'variant_name',
        'variant_value',
        'price_adjustment',
        'cost_adjustment',
        'sku_suffix',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}