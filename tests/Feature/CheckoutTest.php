<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Enums\LedgerType;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase, Builds;

    private User $user;
    private CashierShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeUser();
        $this->actingAs($this->user);
        $this->shift = $this->openShift($this->user);
    }

    private function sell(array $quantities, array $options = [], ?CashierShift $shift = null): Sale
    {
        return app(CheckoutService::class)->checkout($quantities, $options, $shift ?? $this->shift, $this->user->id);
    }

    public function test_cash_sale_creates_invoice_items_and_reduces_stock(): void
    {
        $p = $this->makeProduct(['retail_price' => 10, 'cost_price' => 6]);
        $this->stock($p, 20);

        $sale = $this->sell([$p->id => 3]);

        $this->assertSame('INV-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT), $sale->invoice_number);
        $this->assertMoney(30, $sale->subtotal);
        $this->assertMoney(30, $sale->total);
        $this->assertMoney(30, $sale->paid);
        $this->assertMoney(0, $sale->due_amount);
        $this->assertEquals($this->shift->id, $sale->shift_id);

        $item = $sale->items()->first();
        $this->assertMoney(3, $item->quantity);
        $this->assertMoney(10, $item->unit_price);
        $this->assertMoney(6, $item->cost_price);   // تكلفة الصنف وقت البيع للأرباح

        $this->assertMoney(17, $this->qtyIn($p));
        $this->assertMoney(17, $p->fresh()->stock);
        $this->assertSame(1, StockMovement::where('type', 'sale')->where('reference', $sale->invoice_number)->count());
    }

    public function test_weighed_item_supports_decimal_quantity(): void
    {
        $p = $this->makeProduct(['retail_price' => 4, 'sold_by_weight' => true, 'unit' => 'كغم']);
        $this->stock($p, 10);

        $sale = $this->sell([$p->id => 1.5]);

        $this->assertMoney(6, $sale->total);
        $this->assertMoney(8.5, $this->qtyIn($p));
    }

    public function test_discount_is_applied_and_capped_at_subtotal(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 20);

        $this->assertMoney(25, $this->sell([$p->id => 3], ['discount' => 5])->total);
        $this->assertMoney(0, $this->sell([$p->id => 1], ['discount' => 999])->total);
    }

    public function test_overpayment_gives_change_and_paid_is_capped_at_total(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 5);

        $sale = $this->sell([$p->id => 3], ['paid_amount' => 50]);

        $this->assertMoney(30, $sale->paid);
        $this->assertMoney(20, $sale->change_amount);
        $this->assertMoney(0, $sale->due_amount);
    }

    public function test_credit_sale_records_customer_debt_with_ledger_entry(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 10);
        $c = $this->makeCustomer();

        $sale = $this->sell([$p->id => 3], ['payment_method' => 'credit', 'customer_id' => $c->id]);

        $this->assertMoney(0, $sale->paid);
        $this->assertMoney(30, $sale->due_amount);
        $this->assertMoney(30, $c->fresh()->balance);

        $tx = CustomerTransaction::where('customer_id', $c->id)->first();
        $this->assertSame(LedgerType::Debt, $tx->type);
        $this->assertEquals($sale->id, $tx->sale_id);
        $this->assertMoney(30, $tx->amount);
    }

    public function test_partial_payment_with_customer_leaves_the_rest_as_debt(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 10);
        $c = $this->makeCustomer();

        $sale = $this->sell([$p->id => 3], ['customer_id' => $c->id, 'paid_amount' => 10]);

        $this->assertMoney(10, $sale->paid);
        $this->assertMoney(20, $sale->due_amount);
        $this->assertMoney(20, $c->fresh()->balance);
    }

    public function test_unpaid_remainder_without_customer_is_rejected_and_nothing_is_saved(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 10);

        try {
            $this->sell([$p->id => 3], ['payment_method' => 'credit']);
            $this->fail('كان يجب رفض البيع الآجل بدون عميل');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('اختر عميلاً', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertMoney(10, $this->qtyIn($p));
    }

    public function test_credit_limit_blocks_sale_and_leaves_balance_unchanged(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 10);
        $c = $this->makeCustomer(['credit_limit' => 50]);
        $c->addTransaction(LedgerType::Debt, 40);

        try {
            $this->sell([$p->id => 3], ['payment_method' => 'credit', 'customer_id' => $c->id]);
            $this->fail('كان يجب رفض تجاوز سقف الدين');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('سقف', $e->getMessage());
        }

        $this->assertMoney(40, $c->fresh()->balance);
        $this->assertSame(0, Sale::count());
        $this->assertMoney(10, $this->qtyIn($p));
    }

    public function test_insufficient_stock_rolls_back_the_whole_sale(): void
    {
        $a = $this->makeProduct(['retail_price' => 10]);
        $b = $this->makeProduct(['retail_price' => 10]);
        $this->stock($a, 10);
        $this->stock($b, 2);
        $c = $this->makeCustomer();

        try {
            $this->sell([$a->id => 3, $b->id => 5], ['payment_method' => 'credit', 'customer_id' => $c->id]);
            $this->fail('كان يجب رفض البيع لعدم كفاية المخزون');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('الكمية غير كافية', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, CustomerTransaction::count());
        $this->assertMoney(10, $this->qtyIn($a));   // الصنف الأول لم يُخصم أيضاً
        $this->assertMoney(2, $this->qtyIn($b));
        $this->assertMoney(0, $c->fresh()->balance);
    }

    public function test_closed_shift_cannot_make_sales(): void
    {
        $p = $this->makeProduct();
        $this->stock($p, 5);
        $this->shift->closeWith(0);

        $this->expectException(RuntimeException::class);
        $this->sell([$p->id => 1]);
    }

    public function test_empty_or_invalid_cart_is_rejected(): void
    {
        $inactive = $this->makeProduct(['is_active' => false]);
        $this->stock($inactive, 5);

        $this->expectException(RuntimeException::class);
        $this->sell([$inactive->id => 1, 99999 => 2, 12345 => 0]);
    }

    public function test_invoice_numbers_are_unique_and_sequential(): void
    {
        $p = $this->makeProduct();
        $this->stock($p, 10);

        $a = $this->sell([$p->id => 1]);
        $b = $this->sell([$p->id => 1]);

        $this->assertNotSame($a->invoice_number, $b->invoice_number);
        $this->assertSame($a->id + 1, $b->id);
    }
}
