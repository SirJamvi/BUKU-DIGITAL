<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// HAPUS IMPORT BelongsToBusiness

class ProductVariant extends Model
{
    use HasFactory; // HAPUS BelongsToBusiness DARI SINI

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id', // Pastikan product_id ada di sini
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
