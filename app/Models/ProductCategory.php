<?php

namespace App\Models;

use App\Traits\BelongsToBusiness; // <-- TAMBAHKAN INI
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory, BelongsToBusiness; // <-- TAMBAHKAN TRAIT DI SINI

    protected $table = 'product_categories';

    protected $fillable = [
        'business_id', // <-- TAMBAHKAN INI
        'name',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}