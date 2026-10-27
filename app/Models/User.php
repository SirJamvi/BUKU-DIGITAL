<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasRoles;
use App\Traits\HasPermissions;
use Laravel\Sanctum\HasApiTokens;

/**
 * @method bool hasRole(string|array $roleName)
 * @method bool hasPermission(string $permissionName)
 * @method bool isAdmin()
 * @method bool isKasir()
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPermissions;

    /**
     * Cek apakah user adalah admin atau owner.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'owner']) || $this->hasRole('admin');
    }

    /**
     * Cek apakah user adalah kasir.
     */
    public function isKasir(): bool
    {
        return $this->role === 'kasir' || $this->hasRole('kasir');
    }

    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
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
}
