<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** منطق البيع المالي كاملاً (بعيداً عن الواجهة ليسهل اختباره وحمايته) */
class CheckoutService
{
    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    /**
     * @param  array<int|string, mixed>  $quantities  [product_id => quantity]
     * @param  array{discount?:mixed,customer_id?:mixed,payment_method?:string,paid_amount?:mixed,payment_details?:?string}  $options
     */
    public function checkout(array $quantities, array $options, CashierShift $shift, ?int $userId = null): Sale
    {
        $method = PaymentMethod::tryFrom((string) ($options['payment_method'] ?? 'cash')) ?? PaymentMethod::Cash;
        $customerId = ! empty($options['customer_id']) ? (int) $options['customer_id'] : null;
        $paidInput = $options['paid_amount'] ?? '';

        return DB::transaction(function () use ($quantities, $options, $shift, $userId, $method, $customerId, $paidInput) {
            if (! CashierShift::whereKey($shift->id)->where('status', 'open')->exists()) {
                throw new RuntimeException('الوردية مغلقة، افتح وردية جديدة');
            }

            $warehouse = Warehouse::defaultOrCreate();

            $products = Product::whereIn('id', array_keys($quantities))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()->keyBy('id');

            // الأسعار تُقرأ من قاعدة البيانات وليس من المتصفح
            $lines = [];
            $subtotal = 0.0;

            foreach ($quantities as $id => $qty) {
                $product = $products->get((int) $id);
                $qty = round($this->num($qty), 3);

                if (! $product || $qty <= 0) {
                    continue;
                }

                $lineTotal = round($qty * (float) $product->retail_price, 2);
                $subtotal += $lineTotal;
                $lines[] = compact('product', 'qty', 'lineTotal');
            }

            if (empty($lines)) {
                throw new RuntimeException('لا توجد أصناف صالحة في السلة');
            }

            $subtotal = round($subtotal, 2);
            $discount = min(max(0, $this->num($options['discount'] ?? 0)), $subtotal);
            $total = round($subtotal - $discount, 2);

            $paid = ($paidInput === '' || $paidInput === null)
                ? ($method === PaymentMethod::Credit ? 0.0 : $total)
                : max(0, $this->num($paidInput));

            $due = max(0, round($total - $paid, 2));
            $change = max(0, round($paid - $total, 2));

            if ($due > 0 && ! $customerId) {
                throw new RuntimeException('اختر عميلاً لتسجيل المبلغ المتبقي كدين');
            }

            $customer = null;

            if ($customerId) {
                $customer = Customer::whereKey($customerId)->lockForUpdate()->first();

                if (! $customer) {
                    throw new RuntimeException('العميل غير موجود');
                }

                if ($due > 0
                    && (float) $customer->credit_limit > 0
                    && (float) $customer->balance + $due > (float) $customer->credit_limit) {
                    throw new RuntimeException('تجاوز سقف الدين للعميل: ' . $customer->name);
                }
            }

            $sale = Sale::create([
                'invoice_number' => 'TMP-' . uniqid(),
                'user_id' => $userId ?? auth()->id(),
                'customer_id' => $customerId,
                'warehouse_id' => $warehouse->id,
                'shift_id' => $shift->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => min($paid, $total),
                'change_amount' => $change,
                'due_amount' => $due,
                'payment_method' => $method->value,
                'payment_details' => $method->needsDetails() ? (($options['payment_details'] ?? null) ?: null) : null,
            ]);

            $sale->update(['invoice_number' => 'INV-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            $stock = app(StockService::class);

            foreach ($lines as $l) {
                $sale->items()->create([
                    'product_id' => $l['product']->id,
                    'product_name' => $l['product']->name,
                    'quantity' => $l['qty'],
                    'unit_price' => $l['product']->retail_price,
                    'cost_price' => $l['product']->cost_price,
                    'total' => $l['lineTotal'],
                ]);

                $stock->adjust($l['product']->id, $warehouse->id, -$l['qty'], 'sale', $sale->invoice_number);
            }

            if ($due > 0) {
                $customer->addTransaction(LedgerType::Debt, $due, [
                    'sale_id' => $sale->id,
                    'description' => 'فاتورة ' . $sale->invoice_number,
                ]);
            }

            return $sale;
        });
    }
}
