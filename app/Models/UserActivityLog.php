<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    use HasFactory;

    protected $table = 'user_activity_logs';

    protected $fillable = [
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