<?php

namespace App\Services;

use App\Models\StockTake;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockTakeService
{
    /** اعتماد الجرد: التسوية = الفعلي − رصيد النظام لحظة البدء. يرجع قيمة الفروقات بسعر التكلفة */
    public function approve(StockTake $take): float
    {
        return DB::transaction(function () use ($take) {
            $t = StockTake::whereKey($take->id)->lockForUpdate()->firstOrFail();

            if (! $t->isDraft()) {
                throw new RuntimeException('هذا الجرد معتمد مسبقاً');
            }

            $stock = app(StockService::class);
            $value = 0.0;

            foreach ($t->items()->whereNotNull('counted_qty')->get() as $item) {
                $diff = $item->difference();

                if ($diff == 0.0 || ! $item->product_id) {
                    continue;
                }

                $stock->adjust($item->product_id, $t->warehouse_id, $diff, 'adjustment', $t->number, 'تسوية جرد');
                $value += $diff * (float) $item->cost_price;
            }

            $t->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => auth()->id(),
                'difference_value' => round($value, 2),
            ]);

            return round($value, 2);
        });
    }
}
