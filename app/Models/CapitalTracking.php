<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapitalTracking extends Model
{
    use HasFactory;

    protected $table = 'capital_tracking';

    protected $fillable = [
        'initial_capital',
        'additional_capital',
        'total_returned',
        'status',
        'last_updated',
        'updated_by',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}