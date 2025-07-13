<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class Inventory extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'inventory';

    protected $fillable = [
        'business_id',
        'product_id',
        'current_stock',
        'min_stock',
        'max_stock',
        'last_updated',
        'updated_by',
    ];

    protected $casts = [
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