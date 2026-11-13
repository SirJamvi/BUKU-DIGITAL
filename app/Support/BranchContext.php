<?php

namespace App\Support;

use App\Exceptions\BranchRequiredException;
use App\Models\Branch;
use App\Models\User;

class BranchContext
{
    public const MODE_BRANCH = 'branch';
    public const MODE_CONSOLIDATED = 'consolidated';
    public const MODE_UNSCOPED = 'unscoped';
    public const MODE_NONE = 'none';

    protected string $mode = self::MODE_NONE;
    protected ?int $branchId = null;
    protected ?int $businessId = null;
    protected array $accessibleBranchIdsCache = [];

    /**
     * Set konteks ke cabang spesifik.
     */
    public function setBranch(?int $branchId, ?int $businessId = null): void
    {
        $this->mode = self::MODE_BRANCH;
        $this->branchId = $branchId;
        if ($businessId !== null) {
            $this->businessId = $businessId;
        }
    }

    /**
     * Set konteks ke mode konsolidasi (Admin / Holding: melihat semua cabang di tenant business_id).
     */
    public function setConsolidated(?int $businessId = null): void
    {
        $this->mode = self::MODE_CONSOLIDATED;
        $this->branchId = null;
        if ($businessId !== null) {
            $this->businessId = $businessId;
        }
    }

    /**
     * Set konteks ke mode unscoped (bypasses branch filter untuk webhook, queue job, internal console).
     */
    public function setUnscoped(): void
    {
        $this->mode = self::MODE_UNSCOPED;
        $this->branchId = null;
    }

    /**
     * Set konteks ke mode none (fail-closed untuk tamu atau staf tanpa cabang aktif).
     */
    public function setNone(): void
    {
        $this->mode = self::MODE_NONE;
        $this->branchId = null;
        $this->businessId = null;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getBranchId(): ?int
    {
        return $this->branchId;
    }

    public function getBusinessId(): ?int
    {
        return $this->businessId;
    }

    public function isBranch(): bool
    {
        return $this->mode === self::MODE_BRANCH;
    }

    public function isConsolidated(): bool
    {
        return $this->mode === self::MODE_CONSOLIDATED;
    }

    public function isUnscoped(): bool
    {
        return $this->mode === self::MODE_UNSCOPED;
    }

    public function isNone(): bool
    {
        return $this->mode === self::MODE_NONE;
    }

    /**
     * Terapkan filter branch_id pada Query Builder / Eloquent Builder manual (e.g. DB::table).
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string|null $table Nama tabel / alias tabel (opsional, untuk mencegah ambiguous column)
     * @param string $column Nama kolom branch_id (default: 'branch_id')
     * @return mixed
     */
    public function applyTo($query, ?string $table = null, string $column = 'branch_id')
    {
        $col = $table ? "{$table}.{$column}" : $column;

        if ($this->isBranch() && $this->branchId !== null) {
            return $query->where($col, $this->branchId);
        }

        if ($this->isNone()) {
            return $query->whereRaw('1 = 0');
        }

        // Pada mode consolidated atau unscoped, query tidak difilter branch_id tertentu
        return $query;
    }

    /**
     * Mengharuskan branch_id valid pada mode BRANCH, jika tidak lempar BranchRequiredException.
     */
    public function requireBranchId(): int
    {
        if ($this->mode !== self::MODE_BRANCH || $this->branchId === null) {
            throw new BranchRequiredException('Operasi tulis atau transaksi memerlukan cabang aktif yang spesifik.');
        }

        return $this->branchId;
    }

    /**
     * Mendapatkan daftar ID cabang yang berhak diakses user (di-cache in-memory per request).
     */
    public function accessibleBranchIds(User $user): array
    {
        if (isset($this->accessibleBranchIdsCache[$user->id])) {
            return $this->accessibleBranchIdsCache[$user->id];
        }

        if (method_exists($user, 'activeAccessibleBranches')) {
            $ids = $user->activeAccessibleBranches()
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        } elseif ($user->isAdmin()) {
            $ids = Branch::where('business_id', $user->business_id)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        } else {
            $ids = $user->branches()
                ->where('is_active', true)
                ->pluck('branches.id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        return $this->accessibleBranchIdsCache[$user->id] = $ids;
    }

    /**
     * Jalankan callback dalam konteks cabang tertentu, lalu kembalikan konteks semula.
     */
    public function runAs(?int $branchId, callable $callback, ?int $businessId = null): mixed
    {
        $prevMode = $this->mode;
        $prevBranchId = $this->branchId;
        $prevBusinessId = $this->businessId;

        $this->setBranch($branchId, $businessId);

        try {
            return $callback();
        } finally {
            $this->mode = $prevMode;
            $this->branchId = $prevBranchId;
            $this->businessId = $prevBusinessId;
        }
    }

    /**
     * Jalankan callback dalam konteks unscoped, lalu kembalikan konteks semula.
     */
    public function runUnscoped(callable $callback): mixed
    {
        $prevMode = $this->mode;
        $prevBranchId = $this->branchId;
        $prevBusinessId = $this->businessId;

        $this->setUnscoped();

        try {
            return $callback();
        } finally {
            $this->mode = $prevMode;
            $this->branchId = $prevBranchId;
            $this->businessId = $prevBusinessId;
        }
    }
}

