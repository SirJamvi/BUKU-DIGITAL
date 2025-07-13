<?php

namespace App\Traits;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToBusiness
{
    /**
     * The "booted" method of the model.
     * Ini akan secara otomatis menerapkan Global Scope.
     */
    protected static function booted(): void
    {
        if (Auth::check() && Auth::user()->business_id) {
            static::addGlobalScope('business', function (Builder $builder) {
                // INI PERBAIKANNYA: Mengambil nama tabel dari $builder, bukan dari self::
                $tableName = $builder->getModel()->getTable();
                $builder->where($tableName . '.business_id', Auth::user()->business_id);
            });
        }
    }

    /**
     * Definisikan relasi ke Business.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}