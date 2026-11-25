<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\SaleReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class SaleReturnTest extends TestCase
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

    private function sell(array $q, array $o = []): Sale
    {
        return app(CheckoutService::class)->checkout($q, $o, $this->shift, $this->user->id);
    }

    private function giveBack(Sale $sale, float $qty): SaleReturn
    {
        return app(SaleReturnService::class)->process($sale, [$sale->items()->first()->id => $qty]);
    }

    public function test_cash_sale_return_restores_stock_and_refunds_cash(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $sale = $this->sell([$p->id => 3]);

        $return = $this->giveBack($sale, 1);

        $this->assertMoney(10, $return->total);
        $this->assertMoney(0, $return->debt_reduced);
        $this->assertMoney(10, $return->cash_refund);
        $this->assertSame('cash', $return->refund_method);
        $this->assertEquals($this->shift->id, $return->shift_id);
        $this->assertMoney(48, $this->qtyIn($p));          // 50 - 3 + 1
        $this->assertSame('RET-' . str_pad((string) $return->id, 6, '0', STR_PAD_LEFT), $return->return_number);
    }

    public function test_credit_sale_return_reduces_customer_debt_first(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $c = $this->makeCustomer();
        $sale = $this->sell([$p->id => 3], ['payment_method' => 'credit', 'customer_id' => $c->id]);

        $r1 = $this->giveBack($sale, 1);
        $this->assertMoney(10, $r1->debt_reduced);
        $this->assertMoney(0, $r1->cash_refund);
        $this->assertNull($r1->refund_method);
        $this->assertMoney(20, $c->fresh()->balance);

        $r2 = $this->giveBack($sale, 2);
        $this->assertMoney(20, $r2->debt_reduced);
        $this->assertMoney(0, $c->fresh()->balance);
        $this->assertMoney(50, $this->qtyIn($p));          // رجع كل شيء
    }

    public function test_cannot_return_more_than_sold_even_across_several_returns(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $sale = $this->sell([$p->id => 3]);

        $this->giveBack($sale, 2);

        try {
            $this->giveBack($sale, 2);
            $this->fail('كان يجب رفض إرجاع كمية أكبر من المتاحة');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('أكبر من المتاح', $e->getMessage());
        }

        $this->assertSame(1, SaleReturn::count());
        $this->assertMoney(49, $this->qtyIn($p));
    }

    public function test_invoice_discount_is_allocated_proportionally(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $sale = $this->sell([$p->id => 3], ['discount' => 3]);   // الإجمالي 27

        $return = $this->giveBack($sale, 1);

        $this->assertMoney(9, $return->total);                   // 10 × (27 ÷ 30)
        $this->assertMoney(9, $return->cash_refund);
    }

    public function test_cash_refund_requires_an_open_shift_and_changes_nothing_otherwise(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $sale = $this->sell([$p->id => 3]);
        $this->shift->closeWith(30);

        try {
            $this->giveBack($sale, 1);
            $this->fail('كان يجب رفض الإرجاع النقدي بدون وردية');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('وردية', $e->getMessage());
        }

        $this->assertSame(0, SaleReturn::count());
        $this->assertMoney(47, $this->qtyIn($p));
    }

    public function test_when_customer_already_paid_debt_the_return_is_refunded_in_cash(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 50);
        $c = $this->makeCustomer();
        $sale = $this->sell([$p->id => 3], ['payment_method' => 'credit', 'customer_id' => $c->id]);
        $c->addTransaction(LedgerType::Payment, 30, ['payment_method' => 'cash']);

        $return = $this->giveBack($sale, 1);

        $this->assertMoney(0, $return->debt_reduced);
        $this->assertMoney(10, $return->cash_refund);
        $this->assertSame('cash', $return->refund_method);
        $this->assertMoney(0, $c->fresh()->balance);           // لا يصبح الرصيد سالباً
    }

    public function test_return_with_no_quantity_is_rejected(): void
    {
        $p = $this->makeProduct();
        $this->stock($p, 10);
        $sale = $this->sell([$p->id => 2]);

        $this->expectException(RuntimeException::class);
        $this->giveBack($sale, 0);
    }
}
