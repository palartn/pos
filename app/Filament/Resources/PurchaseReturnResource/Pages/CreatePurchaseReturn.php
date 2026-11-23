<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Enums\LedgerType;
use App\Filament\Resources\PurchaseReturnResource;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = collect($data['items'] ?? [])->filter(fn ($i) => ! empty($i['product_id']))->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['data.items' => 'أضف صنفاً واحداً على الأقل']);
        }

        try {
            return DB::transaction(function () use ($data, $items) {
                $products = Product::whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

                $lines = $items->map(function ($i) use ($products) {
                    $qty = round((float) $i['quantity'], 3);
                    $cost = round((float) $i['unit_cost'], 2);

                    return [
                        'product' => $products[$i['product_id']],
                        'qty' => $qty,
                        'cost' => $cost,
                        'total' => round($qty * $cost, 2),
                    ];
                });

                $total = round($lines->sum('total'), 2);

                $return = PurchaseReturn::create([
                    'return_number' => 'TMP-' . uniqid(),
                    'supplier_id' => $data['supplier_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'user_id' => auth()->id(),
                    'return_date' => $data['return_date'],
                    'total' => $total,
                    'notes' => $data['notes'] ?? null,
                ]);

                $return->update(['return_number' => 'PRT-' . str_pad((string) $return->id, 6, '0', STR_PAD_LEFT)]);

                $stock = app(StockService::class);

                foreach ($lines as $l) {
                    $return->items()->create([
                        'product_id' => $l['product']->id,
                        'product_name' => $l['product']->name,
                        'quantity' => $l['qty'],
                        'unit_cost' => $l['cost'],
                        'total' => $l['total'],
                    ]);

                    // يرفض الإرجاع إن كانت الكمية في المخزن لا تكفي
                    $stock->adjust($l['product']->id, (int) $data['warehouse_id'], -$l['qty'], 'purchase_return', $return->return_number);
                }

                $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
                $credited = round(min($total, (float) $supplier->balance), 2);

                if ($credited > 0) {
                    $supplier->addTransaction(LedgerType::Payment, $credited, [
                        'description' => 'مرتجع شراء ' . $return->return_number,
                    ]);
                }

                $return->update([
                    'credited' => $credited,
                    'refunded' => round($total - $credited, 2),
                ]);

                return $return;
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['data.items' => $e->getMessage()]);
        }
    }
}
