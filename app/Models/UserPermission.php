<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class UserPermission extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'user_permissions';

    protected $fillable = [
        'business_id',
        'user_id',
        'module',
        'action',
        'is_allowed',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}