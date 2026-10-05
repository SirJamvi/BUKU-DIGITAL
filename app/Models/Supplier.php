<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness;

class Supplier extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'payment_terms',
        'created_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi many-to-many ke Product dengan harga khusus per produk (pivot).
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
                    ->withPivot('price')
                    ->withTimestamps();
    }
}