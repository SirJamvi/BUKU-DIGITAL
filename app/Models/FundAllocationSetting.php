<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToBusiness; 

class FundAllocationSetting extends Model
{
   use HasFactory, BelongsToBusiness;

    protected $table = 'fund_allocation_settings';

    protected $fillable = [
        'business_id',
        'allocation_name',
        'percentage',
        'is_active',
        'category',
        'description',
        'sort_order',
        'is_default',
        'min_percentage',
        'max_percentage',
        'is_mandatory',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
