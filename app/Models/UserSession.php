<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class UserSession extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'user_sessions';

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'ip_address',
        'last_activity',
        'login_time',
        'logout_time',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}