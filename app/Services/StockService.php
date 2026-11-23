<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    public const TYPES = [
        'opening' => 'رصيد افتتاحي / إدخال',
        'purchase' => 'شراء',
        'sale' => 'بيع',
        'sale_return' => 'مرتجع بيع',
        'purchase_return' => 'مرتجع شراء',
        'transfer_in' => 'تحويل وارد',
        'transfer_out' => 'تحويل صادر',
        'adjustment' => 'تسوية / جرد',
    ];

    /** تغيير رصيد صنف في مخزن (الكمية موجبة للزيادة وسالبة للنقصان) */
    public function adjust(
        int $productId,
        int $warehouseId,
        float $qty,
        string $type,
        ?string $reference = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $qty, $type, $reference, $notes) {
            ProductStock::firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0]
            );

            $stock = ProductStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $new = round((float) $stock->quantity + $qty, 3);

            if ($new < 0) {
                $name = Product::whereKey($productId)->value('name');
                $have = rtrim(rtrim(number_format((float) $stock->quantity, 3, '.', ''), '0'), '.');

                throw new RuntimeException("الكمية غير كافية للصنف: {$name} (المتوفر {$have})");
            }

            $stock->update(['quantity' => $new]);
            Product::whereKey($productId)->increment('stock', $qty);

            return StockMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $qty,
                'balance_after' => $new,
                'reference' => $reference,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);
        });
    }

    public function transfer(int $productId, int $fromId, int $toId, float $qty, ?string $notes = null): void
    {
        if ($fromId === $toId) {
            throw new RuntimeException('المخزن المصدر والوجهة متطابقان');
        }

        if ($qty <= 0) {
            throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر');
        }

        DB::transaction(function () use ($productId, $fromId, $toId, $qty, $notes) {
            $this->adjust($productId, $fromId, -$qty, 'transfer_out', "إلى مخزن #{$toId}", $notes);
            $this->adjust($productId, $toId, $qty, 'transfer_in', "من مخزن #{$fromId}", $notes);
        });
    }
}
