<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Enums\LedgerType;
use App\Filament\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

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

                $subtotal = round($lines->sum('total'), 2);
                $discount = min(max(0, (float) ($data['discount'] ?? 0)), $subtotal);
                $total = round($subtotal - $discount, 2);
                $paid = round((float) ($data['paid'] ?? 0), 2);

                if ($paid > $total) {
                    throw ValidationException::withMessages(['data.paid' => 'المدفوع أكبر من إجمالي الفاتورة']);
                }

                $purchase = Purchase::create([
                    'invoice_number' => 'TMP-' . uniqid(),
                    'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                    'supplier_id' => $data['supplier_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'user_id' => auth()->id(),
                    'purchase_date' => $data['purchase_date'],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'paid' => $paid,
                    'due_amount' => round($total - $paid, 2),
                    'payment_method' => $paid > 0 ? ($data['payment_method'] ?? 'cash') : null,
                    'payment_details' => $paid > 0 ? ($data['payment_details'] ?? null) : null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $purchase->update(['invoice_number' => 'PUR-' . str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT)]);

                $stock = app(StockService::class);

                foreach ($lines as $l) {
                    $purchase->items()->create([
                        'product_id' => $l['product']->id,
                        'product_name' => $l['product']->name,
                        'quantity' => $l['qty'],
                        'unit_cost' => $l['cost'],
                        'total' => $l['total'],
                    ]);

                    $stock->adjust($l['product']->id, (int) $data['warehouse_id'], $l['qty'], 'purchase', $purchase->invoice_number);

                    // آخر سعر شراء يصبح سعر التكلفة للصنف
                    $l['product']->update(['cost_price' => $l['cost']]);
                }

                // كشف حساب المورد: مستحق بكامل الفاتورة ثم دفعة بالمبلغ المدفوع
                if ($total > 0) {
                    $supplier = Supplier::findOrFail($data['supplier_id']);
                    $label = 'فاتورة شراء ' . $purchase->invoice_number
                        . ($purchase->supplier_invoice_no ? " (فاتورة المورد {$purchase->supplier_invoice_no})" : '');

                    $supplier->addTransaction(LedgerType::Debt, $total, ['description' => $label]);

                    if ($paid > 0) {
                        $supplier->addTransaction(LedgerType::Payment, $paid, [
                            'description' => 'دفعة عند الشراء - ' . $purchase->invoice_number,
                            'payment_method' => $purchase->payment_method,
                            'payment_details' => $purchase->payment_details,
                        ]);
                    }
                }

                return $purchase;
            });
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['data.items' => $e->getMessage()]);
        }
    }
}
