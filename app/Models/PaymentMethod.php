<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'is_active',
    ];

    // Scope untuk mengambil metode yang aktif saja
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}