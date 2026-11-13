<?php

namespace App\Traits;

use App\Models\Business;
use App\Models\User;
use App\Models\UserSession;
use App\Models\TransactionDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToBusiness
{
    /**
     * The "boot" method of the trait.
     * This will automatically apply the Global Scope without colliding with booted().
     */
    public static function bootBelongsToBusiness(): void
    {
        // Models that should NOT be scoped by business_id
        $excludedModels = [
            Business::class,
            User::class,
            UserSession::class,
            TransactionDetail::class,
            \App\Models\Payment::class,
            \App\Models\Permission::class,
            \App\Models\UserPermission::class,
            \App\Models\CustomerSegment::class,
        ];

        if (!in_array(static::class, $excludedModels, true)) {
            static::addGlobalScope('business', function (Builder $builder) {
                $branchContext = app()->bound(\App\Support\BranchContext::class) ? app(\App\Support\BranchContext::class) : null;
                $isUnscoped = $branchContext && $branchContext->isUnscoped();

                if ($isUnscoped) {
                    return;
                }

                if (Auth::check()) {
                    $businessId = Auth::user()->business_id;
                    if ($businessId) {
                        $tableName = $builder->getModel()->getTable();
                        $builder->where($tableName . '.business_id', $businessId);
                    } else {
                        // Fail-closed: user terotentikasi tetapi belum memiliki business_id
                        $builder->whereRaw('1 = 0');
                    }
                }
            });

            static::creating(function ($model) {
                if (Auth::check() && Auth::user()->business_id && empty($model->business_id)) {
                    $model->business_id = Auth::user()->business_id;
                }
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
