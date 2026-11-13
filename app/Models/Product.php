<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToBusiness;
use App\Support\BranchContext;

class Product extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'name',
        'category_id',
        'parent_stock_id',
        'unit',
        'unit_type',
        'is_service',
        'base_price',
        'cost_price',
        'description',
        'image_url',
        'barcode',
        'sku',
        'is_active',
        'is_featured',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'is_service'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Product $product) {
            if ($product->business_id) {
                app(BranchContext::class)->runUnscoped(function () use ($product) {
                    $branches = Branch::where('business_id', $product->business_id)
                        ->where('is_active', true)
                        ->get();

                    foreach ($branches as $branch) {
                        Inventory::firstOrCreate(
                            [
                                'business_id' => $product->business_id,
                                'branch_id'   => $branch->id,
                                'product_id'  => $product->id,
                            ],
                            [
                                'current_stock' => 0,
                                'cost_price'    => (float) ($product->cost_price ?? 0),
                            ]
                        );
                    }
                });
            }
        });
    }

    /**
     * Satu sumber kebenaran penentuan produk jasa/ongkir.
     * Bernilai true jika is_service=true ATAU nama mengandung ongkir/ongkos kirim ATAU unit 'Jalan'.
     * Aman dipanggil sebelum kolom is_service dimigrasi di database.
     */
    public function isService(): bool
    {
        if (array_key_exists('is_service', $this->attributes) && $this->attributes['is_service'] !== null) {
            return (bool) $this->attributes['is_service'];
        }

        $nameLower = strtolower($this->name ?? '');
        return str_contains($nameLower, 'ongkir')
            || str_contains($nameLower, 'ongkos kirim')
            || ($this->unit ?? '') === 'Jalan';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function parentStock(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_stock_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_stock_id');
    }

    public function childStocks()
    {
        return $this->hasMany(Product::class, 'parent_stock_id');
    }

    public function children()
    {
        return $this->hasMany(Product::class, 'parent_stock_id');
    }

    public function conversions()
    {
        return $this->hasMany(ProductConversion::class, 'from_product_id');
    }

    public function conversionsFrom()
    {
        return $this->hasMany(ProductConversion::class, 'from_product_id');
    }

    public function conversionsTo()
    {
        return $this->hasMany(ProductConversion::class, 'to_product_id');
    }

    /**
     * Mendapatkan produk rujukan stok fisik (jika produk turunan, kembalikan parentStock, jika mandiri kembalikan diri sendiri).
     */
    public function getStockProductAttribute(): Product
    {
        if ($this->parent_stock_id && $this->parentStock) {
            return $this->parentStock;
        }
        return $this;
    }

    /**
     * Mendapatkan stok fisik riil yang berlaku.
     */
    public function getEffectiveStockAttribute(): int
    {
        $target = $this->stock_product;
        return (int) ($target->inventory->current_stock ?? 0);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relasi many-to-many ke Supplier dengan harga khusus per supplier (pivot).
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
                    ->withPivot('price')
                    ->withTimestamps();
    }
}