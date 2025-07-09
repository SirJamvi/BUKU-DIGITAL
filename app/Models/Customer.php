<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'join_date',
        'total_purchases',
        'loyalty_points',
        'status',
        'created_by',
    ];

    protected $casts = [
        'join_date' => 'date',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}