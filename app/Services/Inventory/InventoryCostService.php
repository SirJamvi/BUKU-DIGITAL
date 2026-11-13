<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryCostService
{
    /**
     * Hitung rata-rata tertimbang (Weighted Average) HPP per cabang.
     * Pure function, tanpa ketergantungan DB.
     *
     * Rumus:
     * - Jika stok > 0: (stok x HPP lama + qty x harga beli) / (stok + qty)
     * - Jika stok <= 0: harga beli baru (reset baseline)
     *
     * @param int|float $stock Stok saat ini sebelum barang masuk
     * @param float $cost HPP saat ini
     * @param int|float $qty Kuantitas barang masuk
     * @param float $unitCost Harga beli satuan barang masuk
     * @return float HPP baru dibulatkan 2 desimal
     */
    public static function weightedAverage(int|float $stock, float $cost, int|float $qty, float $unitCost): float
    {
        $stock = (float) $stock;
        $cost = (float) $cost;
        $qty = (float) $qty;
        $unitCost = (float) $unitCost;

        if ($qty <= 0) {
            return round($cost, 2);
        }

        if ($stock <= 0) {
            return round($unitCost, 2);
        }

        $totalVal = ($stock * $cost) + ($qty * $unitCost);
        $totalQty = $stock + $qty;

        if ($totalQty <= 0) {
            return round($unitCost, 2);
        }

        return round($totalVal / $totalQty, 2);
    }

    /**
     * Menerima barang masuk ke inventaris cabang dan memperbarui HPP secara transaksional.
     * Digunakan untuk: Restock dari supplier (in), Hasil pecah unit (in), Transfer masuk antarcabang (transfer_in).
     *
     * @param Inventory $inv Instance inventory cabang target
     * @param int|float $qty Kuantitas barang masuk
     * @param float $unitCost Harga beli per unit / HPP perolehan
     * @param string $type Tipe mutasi ('in', 'transfer_in', dll)
     * @param Model|null $reference Model referensi (e.g. Supplier, Transaction, etc.)
     * @param string|null $notes Catatan audit mutasi
     * @return Inventory
     */
    public function receive(
        Inventory $inv,
        int|float $qty,
        float $unitCost,
        string $type = 'in',
        ?Model $reference = null,
        ?string $notes = null
    ): Inventory {
        return DB::transaction(function () use ($inv, $qty, $unitCost, $type, $reference, $notes) {
            // 1. Kunci baris inventory untuk mencegah race condition
            $lockedInv = Inventory::withoutGlobalScopes()
                ->where('id', $inv->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stockBefore = (float) $lockedInv->current_stock;
            $costBefore = (float) ($lockedInv->cost_price ?? 0);

            // 2. Hitung Moving Average HPP cabang
            $newCost = self::weightedAverage($stockBefore, $costBefore, $qty, $unitCost);

            // 3. Perbarui inventaris cabang
            $lockedInv->current_stock = $stockBefore + (float) $qty;
            $lockedInv->cost_price = $newCost;
            $lockedInv->last_updated = now();
            if (Auth::check()) {
                $lockedInv->updated_by = Auth::id();
            }
            $lockedInv->save();

            // 4. Update harga beli acuan master produk (hanya sebagai harga beli terakhir/acuan)
            if ($unitCost > 0) {
                Product::where('id', $lockedInv->product_id)->update(['cost_price' => round($unitCost, 2)]);
            }

            // 5. Catat pergerakan stok (StockMovement)
            $refId = null;
            $refType = null;
            if ($reference) {
                $refType = get_class($reference);
                $refId = (string) $reference->getKey();
            }

            StockMovement::create([
                'business_id'    => $lockedInv->business_id,
                'branch_id'      => $lockedInv->branch_id,
                'product_id'     => $lockedInv->product_id,
                'type'           => $type,
                'quantity'       => $qty,
                'unit_cost'      => $unitCost,
                'reference_id'   => $refId,
                'reference_type' => $refType,
                'notes'          => $notes ?? "Penerimaan stok [{$type}] {$qty} unit @ " . number_format($unitCost, 2, '.', ''),
                'created_by'     => Auth::id(),
            ]);

            return $lockedInv;
        });
    }

    /**
     * Mengeluarkan stok dari inventaris cabang (mis. Karung induk pecah unit, transfer keluar).
     *
     * @param Inventory $inv
     * @param int|float $qty
     * @param string $type
     * @param Model|null $reference
     * @param string|null $notes
     * @return Inventory
     */
    public function issue(
        Inventory $inv,
        int|float $qty,
        string $type = 'out',
        ?Model $reference = null,
        ?string $notes = null
    ): Inventory {
        return DB::transaction(function () use ($inv, $qty, $type, $reference, $notes) {
            $lockedInv = Inventory::withoutGlobalScopes()
                ->where('id', $inv->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStock = (float) $lockedInv->current_stock;
            if ($currentStock < (float) $qty && !in_array($type, ['sale', 'adjustment'])) {
                throw new \RuntimeException("Stok tidak mencukupi. Sisa: {$currentStock}, diminta: {$qty}");
            }

            $lockedInv->current_stock = $currentStock - (float) $qty;
            $lockedInv->last_updated = now();
            if (Auth::check()) {
                $lockedInv->updated_by = Auth::id();
            }
            $lockedInv->save();

            $refId = null;
            $refType = null;
            if ($reference) {
                $refType = get_class($reference);
                $refId = (string) $reference->getKey();
            }

            StockMovement::create([
                'business_id'    => $lockedInv->business_id,
                'branch_id'      => $lockedInv->branch_id,
                'product_id'     => $lockedInv->product_id,
                'type'           => $type,
                'quantity'       => $qty,
                'unit_cost'      => (float) $lockedInv->cost_price,
                'reference_id'   => $refId,
                'reference_type' => $refType,
                'notes'          => $notes ?? "Pengeluaran stok [{$type}] {$qty} unit",
                'created_by'     => Auth::id(),
            ]);

            return $lockedInv;
        });
    }
}

