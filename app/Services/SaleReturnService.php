<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleReturnService
{
    /** أصناف الفاتورة مع الكمية المتبقية القابلة للإرجاع */
    public function returnableItems(Sale $sale): Collection
    {
        $items = $sale->items()->get();

        $returned = SaleReturnItem::whereIn('sale_item_id', $items->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) as q')
            ->groupBy('sale_item_id')
            ->pluck('q', 'sale_item_id');

        return $items->map(fn ($i) => [
            'sale_item_id' => $i->id,
            'product_name' => $i->product_name,
            'sold' => (float) $i->quantity,
            'remaining' => round((float) $i->quantity - (float) ($returned[$i->id] ?? 0), 3),
        ]);
    }

    /**
     * @param  array<int,float>  $lines  [sale_item_id => الكمية المرتجعة]
     */
    public function process(Sale $sale, array $lines, ?string $notes = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $lines, $notes) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $items = $sale->items()->get()->keyBy('id');
            $remaining = $this->returnableItems($sale)->keyBy('sale_item_id');

            // توزيع خصم الفاتورة على الأسطر بالتناسب
            $factor = (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1.0;

            $rows = [];
            $total = 0.0;

            foreach ($lines as $itemId => $qty) {
                $qty = round((float) $qty, 3);

                if ($qty <= 0 || ! $items->has($itemId)) {
                    continue;
                }

                $item = $items[$itemId];
                $left = (float) $remaining[$itemId]['remaining'];

                if ($qty > $left) {
                    throw new RuntimeException("الكمية المرتجعة للصنف {$item->product_name} أكبر من المتاح للإرجاع ({$left})");
                }

                $value = round($qty * (float) $item->unit_price * $factor, 2);
                $total += $value;
                $rows[] = ['item' => $item, 'qty' => $qty, 'value' => $value];
            }

            if (empty($rows)) {
                throw new RuntimeException('حدد كمية مرتجعة لصنف واحد على الأقل');
            }

            $total = round($total, 2);

            // أولاً: يُخصم من الدين المتبقي على العميل من هذه الفاتورة، والباقي يُرد للعميل
            $debtReduced = 0.0;

            if ($sale->customer_id) {
                $customer = Customer::whereKey($sale->customer_id)->lockForUpdate()->first();
                $priorReduced = (float) SaleReturn::where('sale_id', $sale->id)->sum('debt_reduced');
                $openDue = max(0, round((float) $sale->due_amount - $priorReduced, 2));
                $debtReduced = round(min($total, $openDue, (float) $customer->balance), 2);
            }

            $cashRefund = round($total - $debtReduced, 2);
            $method = null;
            $shift = CashierShift::openFor(auth()->id());

            if ($cashRefund > 0) {
                $method = $sale->payment_method === 'credit' ? 'cash' : $sale->payment_method;

                if ($method === 'cash' && ! $shift) {
                    throw new RuntimeException('افتح وردية أولاً لإرجاع مبلغ نقدي للعميل');
                }
            }

            $return = SaleReturn::create([
                'return_number' => app(NumberSequence::class)->next('sale_return'),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $sale->warehouse_id ?? Warehouse::defaultOrCreate()->id,
                'shift_id' => $shift?->id,
                'user_id' => auth()->id(),
                'total' => $total,
                'debt_reduced' => $debtReduced,
                'cash_refund' => $cashRefund,
                'refund_method' => $method,
                'notes' => $notes,
            ]);

            $stock = app(StockService::class);

            foreach ($rows as $r) {
                $return->items()->create([
                    'sale_item_id' => $r['item']->id,
                    'product_id' => $r['item']->product_id,
                    'product_name' => $r['item']->product_name,
                    'quantity' => $r['qty'],
                    'unit_price' => $r['item']->unit_price,
                    'total' => $r['value'],
                ]);

                // الصنف يعود إلى المخزن الذي بيع منه
                if ($r['item']->product_id) {
                    $stock->adjust($r['item']->product_id, (int) $return->warehouse_id, $r['qty'], 'sale_return', $return->return_number);
                }
            }

            if ($debtReduced > 0) {
                $customer->addTransaction(LedgerType::Payment, $debtReduced, [
                    'description' => 'مرتجع بيع ' . $return->return_number . ' (فاتورة ' . $sale->invoice_number . ')',
                ]);
            }

            return $return;
        });
    }
}
