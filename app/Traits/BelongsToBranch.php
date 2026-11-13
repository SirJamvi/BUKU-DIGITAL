<?php

namespace App\Traits;

use App\Exceptions\BranchRequiredException;
use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    /**
     * Inisialisasi trait saat model di-boot.
     * Menerapkan Global Scope tingkat 2 (Branch Isolation) berbasis BranchContext singleton.
     */
    public static function bootBelongsToBranch(): void
    {
        // 1. GLOBAL SCOPE: 'branch'
        static::addGlobalScope('branch', function (Builder $builder) {
            /** @var BranchContext $context */
            $context = app(BranchContext::class);

            // A. Mode UNSCOPED (Job / Webhook / Internal) atau CONSOLIDATED (Admin Holding):
            // Tidak menerapkan filter branch_id (semua cabang terlihat dalam scope business_id)
            if ($context->isUnscoped() || $context->isConsolidated()) {
                return;
            }

            // B. Mode BRANCH: Filter ketat berdasarkan active branch ID
            if ($context->isBranch()) {
                $tableName = $builder->getModel()->getTable();
                $builder->where($tableName . '.branch_id', $context->getBranchId());
                return;
            }

            // C. Mode NONE: Fail-closed (guest atau staf tanpa cabang aktif yang valid)
            $builder->whereRaw('1 = 0');
        });

        // 2. CREATING EVENT: Validasi integritas penulisan data
        static::creating(function (Model $model) {
            /** @var BranchContext $context */
            $context = app(BranchContext::class);

            // A. Mode CONSOLIDATED: Penulisan dilarang keras (Hanya Read-Only)
            if ($context->isConsolidated()) {
                throw new BranchRequiredException(
                    "Operasi tulis pada " . class_basename($model) . " tidak diizinkan dalam mode Pusat (Konsolidasi). Pilih cabang di menu Monitoring Cabang untuk melakukan input."
                );
            }

            // B. Mode NONE: Fail-closed jika branch_id belum diset manual
            if ($context->isNone() && empty($model->branch_id)) {
                throw new BranchRequiredException(
                    "Operasi simpan pada " . class_basename($model) . " memerlukan cabang aktif yang valid."
                );
            }

            // C. Mode BRANCH: Otomatis tetapkan branch_id jika belum diisi manual
            if ($context->isBranch() && empty($model->branch_id)) {
                $model->branch_id = $context->getBranchId();
            }
        });
    }

    /**
     * Relasi ke model Branch.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Local scope untuk menyaring query ke cabang tertentu secara eksplisit.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->withoutGlobalScope('branch')
            ->where($this->getTable() . '.branch_id', $branchId);
    }

    /**
     * Local scope untuk mengabaikan isolasi cabang (melihat semua cabang dalam tenant business).
     */
    public function scopeAllBranches(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
