<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToBusiness;

class Branch extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $table = 'branches';

    protected $fillable = [
        'business_id',
        'name',
        'code',
        'is_main',
        'address',
        'phone',
        'fonnte_token',
        'fonnte_device_id',
        'manager_id',
        'is_active',
    ];

    protected $casts = [
        'is_main'   => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Bisnis/Organisasi pemilik cabang ini.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Manajer / Penanggung jawab cabang.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Daftar staf (kasir, driver, admin) yang ditugaskan di cabang ini (Many-to-Many).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * Stok inventori fisik pada cabang ini.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'branch_id');
    }

    /**
     * Seluruh transaksi penjualan/pembelian yang tercatat di cabang ini.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'branch_id');
    }

    /**
     * Catatan arus kas laci kasir & operasional cabang ini.
     */
    public function cashFlows(): HasMany
    {
        return $this->hasMany(CashFlow::class, 'branch_id');
    }

    /**
     * Riwayat mutasi stok masuk/keluar di cabang ini.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'branch_id');
    }

    /**
     * Antrean pesanan WhatsApp AI Fonnte yang masuk ke cabang ini.
     */
    public function temporaryOrders(): HasMany
    {
        return $this->hasMany(TemporaryOrder::class, 'branch_id');
    }

    /**
     * Saldo modal awal harian laci kasir cabang.
     */
    public function openingBalances(): HasMany
    {
        return $this->hasMany(CashierOpeningBalance::class, 'branch_id');
    }

    /**
     * Scope hanya cabang yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Cek apakah cabang ini sudah terkonfigurasi WhatsApp Fonnte.
     */
    public function hasFonnteConfigured(): bool
    {
        return !empty($this->fonnte_token);
    }
}

