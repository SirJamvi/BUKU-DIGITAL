<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasRoles;
use App\Traits\HasPermissions;
use Laravel\Sanctum\HasApiTokens;

/**
 * @method bool hasRole(string|array $roles)
 * @method bool hasPermission(string $permissionName)
 * @method bool isAdmin()
 * @method bool isKasir()
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPermissions;

    /**
     * Daftar opsi Role Utama (sesuai ENUM users.role).
     */
    public const PRIMARY_ROLES = [
        'admin'  => 'Admin',
        'kasir'  => 'Kasir',
        'driver' => 'Driver',
        'ko'     => 'KO',
    ];

    /**
     * Daftar opsi Role Tambahan / Rangkap Jabatan (users.secondary_roles).
     */
    public const SECONDARY_ROLES = [
        'admin'  => 'Admin',
        'ko'     => 'KO',
        'kasir'  => 'Kasir',
        'driver' => 'Driver',
        'sales'  => 'Sales',
    ];

    /**
     * Mengembalikan seluruh role user (Role Utama + Role Tambahan) secara unik dalam huruf kecil.
     *
     * @return array<int, string>
     */
    public function allRoles(): array
    {
        $roles = [];

        if (is_string($this->role) && trim($this->role) !== '') {
            $roles[] = strtolower(trim($this->role));
        }

        if (is_array($this->secondary_roles)) {
            foreach ($this->secondary_roles as $secondaryRole) {
                if (is_string($secondaryRole) && trim($secondaryRole) !== '') {
                    $roles[] = strtolower(trim($secondaryRole));
                }
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * Cek apakah user memiliki salah satu role yang diminta (baik Role Utama maupun Role Tambahan).
     *
     * @param string|array<int, string> $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $userRoles = $this->allRoles();
        $requestedRoles = is_array($roles) ? $roles : [$roles];

        foreach ($requestedRoles as $requested) {
            if (!is_string($requested) || trim($requested) === '') {
                continue;
            }

            if (in_array(strtolower(trim($requested)), $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope query untuk memfilter user yang memiliki salah satu role di `role` utama atau `secondary_roles` JSON.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array<int, string> $roles
     */
    public function scopeWithRole($query, string|array $roles)
    {
        $roleList = array_values(array_unique(array_filter(array_map(
            fn ($r) => is_string($r) ? strtolower(trim($r)) : '',
            (array) $roles
        ))));

        if (empty($roleList)) {
            return $query;
        }

        return $query->where(function ($q) use ($roleList) {
            $q->whereIn('role', $roleList);
            foreach ($roleList as $role) {
                $q->orWhereJsonContains('secondary_roles', $role);
            }
        });
    }

    /**
     * Cek apakah user adalah admin atau owner.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'owner']);
    }

    /**
     * Cek apakah user adalah kasir.
     */
    public function isKasir(): bool
    {
        return $this->hasRole('kasir');
    }

    protected $fillable = [
        'business_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'secondary_roles',
        'permissions',
        'created_by',
        'is_active',
        'transaction_limit',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'secondary_roles' => 'array',
        'permissions' => 'json',
        'is_active' => 'boolean',
    ];

    /**
     * The business that this user belongs to.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * User yang membuat user ini.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Transaksi yang dibuat oleh user ini.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    /**
     * Sesi login user.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Log aktivitas user.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    /**
     * Cabang default/utama tempat user ditugaskan.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Daftar seluruh cabang yang dapat diakses oleh user ini (Many-to-Many).
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * Mendapatkan seluruh cabang aktif yang dapat diakses oleh user ini.
     * - Staf: melalui pivot branch_user dengan kondisi branches.is_active = 1.
     * - Admin/Owner: seluruh cabang aktif dalam bisnis miliknya.
     */
    public function activeAccessibleBranches(): \Illuminate\Support\Collection
    {
        if ($this->isAdmin()) {
            return Branch::where('business_id', $this->business_id)
                ->where('is_active', true)
                ->get();
        }

        return $this->branches()
            ->where('branches.is_active', true)
            ->get();
    }

    /**
     * Mendapatkan ID cabang yang aktif saat ini dari session atau fallback cabang penugasan aktif.
     * Mengembalikan null jika mode 'all' (Pusat / Konsolidasi untuk Admin).
     */
    public function getActiveBranchId(): ?int
    {
        if (session()->has('active_branch_id')) {
            $sessionVal = session('active_branch_id');
            if ($sessionVal === 'all') {
                return null;
            }
            return (int) $sessionVal;
        }

        // Staf cabang (kasir/driver) wajib memiliki cabang aktif
        if (!$this->isAdmin()) {
            $accessible = $this->activeAccessibleBranches();
            $default = $accessible->first(fn($b) => (bool) ($b->pivot->is_default ?? false));
            return $default?->id ?? $accessible->first()?->id;
        }

        // Admin defaultnya adalah mode Pusat (konsolidasi = null)
        return null;
    }

    /**
     * Mendapatkan instance model Branch yang sedang aktif.
     */
    public function getActiveBranch(): ?Branch
    {
        $branchId = $this->getActiveBranchId();
        return $branchId ? Branch::find($branchId) : null;
    }

    /**
     * Mengubah cabang aktif user pada session.
     * $branch bisa berupa instance Branch, int branch_id, atau string 'all' untuk konsolidasi admin.
     */
    public function setActiveBranch($branch): void
    {
        if ($branch instanceof Branch) {
            session([
                'active_branch_id' => $branch->id,
                'active_branch_name' => $branch->name,
                'active_branch_code' => $branch->code,
            ]);
        } elseif ($branch === 'all' || $branch === null) {
            session([
                'active_branch_id' => 'all',
                'active_branch_name' => 'Semua Cabang (Konsolidasi)',
                'active_branch_code' => 'ALL',
            ]);
        } else {
            $branchModel = Branch::find($branch);
            if ($branchModel) {
                session([
                    'active_branch_id' => $branchModel->id,
                    'active_branch_name' => $branchModel->name,
                    'active_branch_code' => $branchModel->code,
                ]);
            }
        }
    }

    /**
     * Validasi apakah user berhak mengakses cabang tertentu.
     */
    public function hasAccessToBranch($branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : (int) $branch;
        if ($branchId <= 0) {
            return false;
        }

        return $this->activeAccessibleBranches()->contains('id', $branchId);
    }

    /**
     * Cek apakah staf ditugaskan di lebih dari satu cabang (Staff Mobility).
     */
    public function isMultiBranchStaff(): bool
    {
        return $this->branches()->count() > 1;
    }

    /**
     * Sinkronisasi penugasan cabang untuk user staf (Single Source of Truth).
     * Jika role adalah admin, pivot dibersihkan dan branch_id dinullkan.
     */
    public function syncBranches(array $branchIds, ?int $defaultId = null): void
    {
        if ($this->isAdmin()) {
            $this->branches()->detach();
            $this->forceFill(['branch_id' => null])->save();
            return;
        }

        $cleanIds = array_values(array_filter(array_unique(array_map('intval', $branchIds)), fn($id) => $id > 0));

        if (empty($cleanIds)) {
            $this->branches()->detach();
            $this->forceFill(['branch_id' => null])->save();
            return;
        }

        if ($defaultId === null || !in_array($defaultId, $cleanIds, true)) {
            $defaultId = $cleanIds[0];
        }

        $syncData = [];
        foreach ($cleanIds as $id) {
            $syncData[$id] = ['is_default' => ($id === $defaultId)];
        }

        $this->branches()->sync($syncData);
        $this->forceFill(['branch_id' => $defaultId])->save();
    }
}
