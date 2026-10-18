<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Support\Qty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseService
{
    /**
     * إنشاء فاتورة شراء: تزيد المخزون، تحدّث سعر الشراء لآخر تكلفة،
     * تُسجَّل على حساب المورد، وإن وُجدت دفعة تُسجَّل أيضاً.
     */
    public function create(array $data): Purchase
    {
        $lines = collect($data['lines'] ?? [])->values();

        if ($lines->isEmpty()) {
            throw new RuntimeException('أضف صنفاً واحداً على الأقل');
        }

        $supplierId = (int) ($data['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            throw new RuntimeException('اختر المورد');
        }

        return DB::transaction(function () use ($data, $lines, $supplierId) {
            $products = Product::whereIn('id', $lines->pluck('product_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $items = [];
            $subtotal = 0.0;

            foreach ($lines as $line) {
                $product = $products->get($line['product_id'] ?? null);

                if (! $product) {
                    throw new RuntimeException('أحد المنتجات غير موجود');
                }

                $qty = round((float) ($line['quantity'] ?? 0), 3);
                $cost = round((float) ($line['unit_cost'] ?? 0), 2);

                if ($qty <= 0) {
                    throw new RuntimeException('الكمية يجب أن تكون أكبر من صفر: ' . $product->name);
                }

                if ($cost < 0) {
                    throw new RuntimeException('التكلفة لا يمكن أن تكون سالبة: ' . $product->name);
                }

                $lineTotal = round($qty * $cost, 2);
                $subtotal += $lineTotal;

                $items[] = compact('product', 'qty', 'cost', 'lineTotal');
            }

            $subtotal = round($subtotal, 2);
            $discount = round(max(0, (float) ($data['discount'] ?? 0)), 2);

            if ($discount > $subtotal) {
                throw new RuntimeException('الخصم أكبر من مجموع الأصناف');
            }

            $total = round($subtotal - $discount, 2);
            $paid = round(max(0, (float) ($data['paid'] ?? 0)), 2);

            $supplierInvoice = trim((string) ($data['supplier_invoice_number'] ?? ''));
            $notes = trim((string) ($data['notes'] ?? ''));

            $purchase = Purchase::create([
                'invoice_number' => 'PUR-' . now()->format('YmdHis') . rand(10, 99),
                'supplier_invoice_number' => $supplierInvoice !== '' ? $supplierInvoice : null,
                'supplier_id' => $supplierId,
                'user_id' => Auth::id(),
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $descriptions = [];

            foreach ($items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['qty'],
                    'unit_cost' => $item['cost'],
                    'total' => $item['lineTotal'],
                ]);

                // زيادة المخزون وتحديث سعر الشراء لآخر تكلفة
                $item['product']->increment('quantity', $item['qty'], [
                    'purchase_price' => $item['cost'],
                ]);

                $descriptions[] = $item['product']->name
                    . ' ×' . Qty::format($item['qty'])
                    . ' (' . number_format($item['cost'], 2) . ')';
            }

            $accounts = app(SupplierAccount::class);

            if ($total > 0) {
                $description = 'فاتورة شراء ' . $purchase->invoice_number
                    . ($supplierInvoice !== '' ? ' (فاتورة المورد ' . $supplierInvoice . ')' : '')
                    . ': ' . implode('، ', $descriptions)
                    . ($notes !== '' ? ' — ملاحظة: ' . $notes : '');

                $accounts->addPurchase($supplierId, $total, $purchase, $description);
            }

            if ($paid > 0) {
                $method = (string) ($data['payment_method'] ?? '');

                if ($method === '') {
                    throw new RuntimeException('اختر طريقة الدفع');
                }

                $accounts->pay(
                    $supplierId,
                    $paid,
                    $method,
                    $data['payment_reference'] ?? null,
                    'دفعة مع فاتورة الشراء ' . $purchase->invoice_number,
                    (bool) ($data['from_drawer'] ?? true),
                    $purchase
                );
            }

            return $purchase;
        });
    }
}
