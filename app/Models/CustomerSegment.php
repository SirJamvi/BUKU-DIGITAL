<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class CustomerSegment extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'customer_segments';

    protected $fillable = [
        'business_id',
        'name',
        'criteria',
        'description',
        'created_by',
    ];

    protected $casts = [
        'criteria' => 'json',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}