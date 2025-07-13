<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class UserActivityLog extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'user_activity_logs';

    protected $fillable = [
        'business_id',
        'user_id',
        'action',
        'module',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}