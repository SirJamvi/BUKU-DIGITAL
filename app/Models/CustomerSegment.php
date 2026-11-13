<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use HasFactory;

    protected $table = 'customer_segments';

    protected $fillable = [
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