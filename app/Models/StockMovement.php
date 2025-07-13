<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness;

class StockMovement extends Model
{
   use HasFactory, BelongsToBusiness;
   
   protected $table = 'stock_movements';

   protected $fillable = [
       'business_id',
       'product_id',
       'type',
       'quantity',
       'reference_id',
       'notes',
       'created_by',
       'occurred_at',
   ];

   protected $casts = [
       'occurred_at' => 'datetime',
   ];

   public function product()
   {
       return $this->belongsTo(Product::class);
   }

   public function createdBy()
   {
       return $this->belongsTo(User::class, 'created_by');
   }
}