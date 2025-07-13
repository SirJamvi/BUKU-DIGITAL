<?php

namespace App\Traits;

use App\Models\Business;
use App\Models\User;
use App\Models\UserSession;
use App\Models\TransactionDetail; // <-- TAMBAHKAN INI
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToBusiness
{
    /**
     * The "booted" method of the model.
     * This will automatically apply the Global Scope.
     */
    protected static function booted(): void
    {
        // Models that should NOT be scoped by business_id
        $excludedModels = [
            User::class,
            UserSession::class,
            TransactionDetail::class, // <-- TAMBAHKAN INI KE DAFTAR PENGECUALIAN
            // Add other system-wide models here if needed in the future
        ];

        // Only apply the scope if the current model is not in the excluded list
        if (Auth::check() && Auth::user()->business_id && !in_array(static::class, $excludedModels)) {
            static::addGlobalScope('business', function (Builder $builder) {
                $tableName = $builder->getModel()->getTable();
                $builder->where($tableName . '.business_id', Auth::user()->business_id);
            });
        }
    }

    /**
     * Define the relationship to the Business.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}