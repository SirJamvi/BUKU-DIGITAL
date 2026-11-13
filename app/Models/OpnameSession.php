<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;
use App\Traits\BelongsToBranch;

class OpnameSession extends Model
{
    use HasFactory, BelongsToBusiness, BelongsToBranch;

    protected $table = 'opname_sessions';

    protected $fillable = [
        'business_id',
        'branch_id',
        'created_by',
        'items_count',
        'difference_count',
        'total_system',
        'total_actual',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

