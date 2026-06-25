<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness;

class ProductConversion extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'product_conversions';

    protected $fillable = [
        'business_id',
        'from_product_id',
        'to_product_id',
        'quantity_to_break',
        'yield_amount',
    ];

    public function fromProduct()
    {
        return $this->belongsTo(Product::class, 'from_product_id');
    }

    public function toProduct()
    {
        return $this->belongsTo(Product::class, 'to_product_id');
    }
}
