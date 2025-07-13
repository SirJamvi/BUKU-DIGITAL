<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapitalTracking extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'capital_tracking';

    protected $fillable = [
        'business_id',
        'amount_added',
        'amount_withdrawn',
        'recorded_at',
        'initial_capital',
        'additional_capital',
        'total_returned',
        'status',
        'last_updated',
        'updated_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'last_updated' => 'datetime',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}