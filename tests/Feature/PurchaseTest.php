<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase, Builds;

    private function data(Supplier $s, array $items, array $extra = []): array
    {
        return array_merge([
            'supplier_id' => $s->id,
            'warehouse_id' => Warehouse::defaultOrCreate()->id,
            'purchase_date' => '2026-11-24',
            'return_date' => '2026-11-24',
            'discount' => 0,
            'paid' => 0,
            'items' => array_map(fn ($i) => ['product_id' => $i[0]->id, 'quantity' => $i[1], 'unit_cost' => $i[2]], $items),
        ], $extra);
    }

    public function test_receiving_a_purchase_updates_stock_cost_and_supplier_statement(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct(['cost_price' => 1]);
        $b = $this->makeProduct(['cost_price' => 1]);

        $purchase = app(PurchaseService::class)->receive(
            $this->data($s, [[$a, 10, 4], [$b, 5, 2]], ['paid' => 20, 'payment_method' => 'cash'])
        );

        $this->assertSame('PUR-000001', $purchase->invoice_number);
        $this->assertMoney(50, $purchase->total);
        $this->assertMoney(20, $purchase->paid);
        $this->assertMoney(30, $purchase->due_amount);

        $this->assertMoney(10, $this->qtyIn($a));
        $this->assertMoney(5, $this->qtyIn($b));
        $this->assertMoney(4, $a->fresh()->cost_price);          // آخر سعر شراء

        $this->assertMoney(30, $s->fresh()->balance);
        $txs = SupplierTransaction::where('supplier_id', $s->id)->orderBy('id')->get();
        $this->assertCount(2, $txs);
        $this->assertSame(LedgerType::Debt, $txs[0]->type);
        $this->assertMoney(50, $txs[0]->amount);
        $this->assertSame(LedgerType::Payment, $txs[1]->type);
        $this->assertSame('cash', $txs[1]->payment_method);
        $this->assertMoney(30, $txs[1]->balance_after);
    }

    public function test_discount_reduces_the_total_owed(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct();

        $purchase = app(PurchaseService::class)->receive($this->data($s, [[$a, 10, 5]], ['discount' => 8]));

        $this->assertMoney(42, $purchase->total);
        $this->assertMoney(42, $s->fresh()->balance);
    }

    public function test_paid_more_than_total_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct();

        try {
            app(PurchaseService::class)->receive($this->data($s, [[$a, 2, 5]], ['paid' => 11]));
            $this->fail('كان يجب رفض مدفوع أكبر من الإجمالي');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('data.paid', $e->errors());
        }

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertMoney(0, $s->fresh()->balance);
    }

    public function test_purchase_without_items_is_rejected(): void
    {
        $s = $this->makeSupplier();

        $this->expectException(ValidationException::class);
        app(PurchaseService::class)->receive($this->data($s, []));
    }

    public function test_return_is_credited_against_supplier_balance_first(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct();
        app(PurchaseService::class)->receive($this->data($s, [[$a, 10, 4]], ['paid' => 10]));   // الرصيد 30

        $return = app(PurchaseService::class)->returnToSupplier($this->data($s, [[$a, 10, 4]]));   // قيمة 40

        $this->assertMoney(40, $return->total);
        $this->assertMoney(30, $return->credited);
        $this->assertMoney(10, $return->refunded);            // الزيادة تُسترد نقداً
        $this->assertMoney(0, $s->fresh()->balance);
        $this->assertMoney(0, $this->qtyIn($a));
        $this->assertSame('PRT-000001', $return->return_number);
    }

    public function test_return_smaller_than_balance_is_fully_credited(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct();
        app(PurchaseService::class)->receive($this->data($s, [[$a, 10, 5]]));                   // الرصيد 50

        $return = app(PurchaseService::class)->returnToSupplier($this->data($s, [[$a, 4, 5]]));

        $this->assertMoney(20, $return->credited);
        $this->assertMoney(0, $return->refunded);
        $this->assertMoney(30, $s->fresh()->balance);
    }

    public function test_return_more_than_stock_is_rejected_and_rolled_back(): void
    {
        $this->actingAs($this->makeUser());
        $s = $this->makeSupplier();
        $a = $this->makeProduct();
        app(PurchaseService::class)->receive($this->data($s, [[$a, 3, 5]]));

        try {
            app(PurchaseService::class)->returnToSupplier($this->data($s, [[$a, 5, 5]]));
            $this->fail('كان يجب رفض إرجاع كمية أكبر من الموجودة');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('الكمية غير كافية', $e->getMessage());
        }

        $this->assertSame(0, PurchaseReturn::count());
        $this->assertMoney(3, $this->qtyIn($a));
        $this->assertMoney(15, $s->fresh()->balance);
    }
}
