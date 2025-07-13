<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class TransactionDetail extends Model
{
use HasFactory, BelongsToBusiness;
    protected $table = 'transaction_details';

    protected $fillable = [
        'business_id',
        'transaction_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}