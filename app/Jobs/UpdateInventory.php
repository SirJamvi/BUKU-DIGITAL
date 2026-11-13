<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Support\BranchContext;
use Exception;

/**
 * @deprecated Fase 2: Gunakan InventoryCostService atau PosService secara transaksional per cabang.
 */
class UpdateInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    protected $productId;
    /**
     * @var int
     */
    protected $quantityChange;
    /**
     * @var string
     */
    protected $movementType;
    /**
     * @var string|null
     */
    protected $notes;
    /**
     * @var int|null
     */
    protected $userId;
    /**
     * @var int|null
     */
    protected $branchId;

    /**
     * Buat instance job baru.
     *
     * @param int $productId
     * @param int $quantityChange
     * @param string $movementType ('in', 'out', 'adjustment')
     * @param string|null $notes
     * @param int|null $userId
     * @param int|null $branchId
     */
    public function __construct(
        int $productId,
        int $quantityChange,
        string $movementType,
        ?string $notes = null,
        ?int $userId = null,
        ?int $branchId = null
    ) {
        $this->productId = $productId;
        $this->quantityChange = $quantityChange;
        $this->movementType = $movementType;
        $this->notes = $notes;
        $this->userId = $userId;
        $this->branchId = $branchId;
    }

    /**
     * Jalankan job.
     *
     * @return void
     */
    public function handle(): void
    {
        $context = app(BranchContext::class);
        $runner = function () {
            try {
                $product = Product::findOrFail($this->productId);
                $inventoryQuery = Inventory::where('product_id', $this->productId);
                if ($this->branchId) {
                    $inventoryQuery->where('branch_id', $this->branchId);
                }
                $inventory = $inventoryQuery->first();
                if ($inventory) {
                    $inventory->increment('current_stock', $this->quantityChange);
                }

                StockMovement::create([
                    'business_id' => $inventory ? $inventory->business_id : $product->business_id,
                    'branch_id'   => $this->branchId ?? ($inventory ? $inventory->branch_id : null),
                    'product_id'  => $this->productId,
                    'type'        => $this->movementType,
                    'quantity'    => $this->quantityChange,
                    'notes'       => $this->notes ?? 'Pembaruan stok via job (deprecated).',
                    'created_by'  => $this->userId,
                ]);

            } catch (Exception $e) {
                logger()->error("Gagal menjalankan job UpdateInventory untuk produk ID: {$this->productId}. Error: " . $e->getMessage());
            }
        };

        if ($this->branchId) {
            $context->runAs($this->branchId, $runner);
        } else {
            $context->runUnscoped($runner);
        }
    }
}