<?php

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Filament\Resources\StockTakeResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockTake;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockTake extends CreateRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $warehouseId = (int) $data['warehouse_id'];

        if (StockTake::where('warehouse_id', $warehouseId)->where('status', 'draft')->exists()) {
            throw ValidationException::withMessages([
                'data.warehouse_id' => 'يوجد جرد مسودة غير معتمد لهذا المخزن، اعتمده أو احذفه أولاً',
            ]);
        }

        $categoryIds = $data['category_ids'] ?? [];

        $products = Product::query()
            ->where('is_active', true)
            ->when(! empty($categoryIds), fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->orderBy('name')
            ->get(['id', 'name', 'barcode', 'cost_price']);

        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['data.category_ids' => 'لا توجد أصناف مطابقة لبدء الجرد']);
        }

        return DB::transaction(function () use ($data, $warehouseId, $products) {
            $take = StockTake::create([
                'number' => 'TMP-' . uniqid(),
                'warehouse_id' => $warehouseId,
                'user_id' => auth()->id(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $take->update(['number' => 'STK-' . str_pad((string) $take->id, 6, '0', STR_PAD_LEFT)]);

            $stocks = ProductStock::where('warehouse_id', $warehouseId)->pluck('quantity', 'product_id');
            $now = now();

            $rows = $products->map(fn ($p) => [
                'stock_take_id' => $take->id,
                'product_id' => $p->id,
                'product_name' => $p->name,
                'barcode' => $p->barcode,
                'system_qty' => (float) ($stocks[$p->id] ?? 0),
                'counted_qty' => null,
                'cost_price' => $p->cost_price,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('stock_take_items')->insert($chunk);
            }

            return $take;
        });
    }
}
