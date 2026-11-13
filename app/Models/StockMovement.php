<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;
use App\Traits\BelongsToBranch;

class StockMovement extends Model
{
   use HasFactory, BelongsToBusiness, BelongsToBranch;
   
   protected $table = 'stock_movements';

   protected $fillable = [
       'business_id',
       'branch_id',
       'destination_branch_id',
       'product_id',
       'type',
       'quantity',
       'unit_cost',
       'reference_id',
       'reference_type',
       'notes',
       'created_by',
   ];

   protected $casts = [
       'unit_cost'   => 'float',
   ];

   public function product(): BelongsTo
   {
       return $this->belongsTo(Product::class);
   }

   public function createdBy(): BelongsTo
   {
       return $this->belongsTo(User::class, 'created_by');
   }

   public function destinationBranch(): BelongsTo
   {
       return $this->belongsTo(Branch::class, 'destination_branch_id');
   }
}
