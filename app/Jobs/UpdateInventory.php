<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use App\Models\StockMovement;
use Exception;

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
     * Buat instance job baru.
     *
     * @param int $productId
     * @param int $quantityChange
     * @param string $movementType ('in', 'out', 'adjustment')
     * @param string|null $notes
     * @param int|null $userId
     */
    public function __construct(int $productId, int $quantityChange, string $movementType, ?string $notes = null, ?int $userId = null)
    {
        $this->productId = $productId;
        $this->quantityChange = $quantityChange;
        $this->movementType = $movementType;
        $this->notes = $notes;
        $this->userId = $userId;
    }

    /**
     * Jalankan job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $product = Product::with('inventory')->findOrFail($this->productId);
            
            // Perbarui stok
            $product->inventory->increment('current_stock', $this->quantityChange);

            // Catat pergerakan stok
            StockMovement::create([
                'product_id' => $this->productId,
                'type' => $this->movementType,
                'quantity' => $this->quantityChange,
                'notes' => $this->notes ?? 'Pembaruan stok via job.',
                'created_by' => $this->userId,
            ]);

        } catch (Exception $e) {
            logger()->error("Gagal menjalankan job UpdateInventory untuk produk ID: {$this->productId}. Error: " . $e->getMessage());
        }
    }
}