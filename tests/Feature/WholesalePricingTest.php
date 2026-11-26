<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Builds;
use Tests\TestCase;

class WholesalePricingTest extends TestCase
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

    private function sell(array $q, array $o = [])
    {
        return app(CheckoutService::class)->checkout($q, $o, $this->shift, $this->user->id);
    }

    public function test_price_for_uses_wholesale_only_when_defined(): void
    {
        $with = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => 8]);
        $without = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => null]);
        $zero = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => 0]);

        $this->assertMoney(8, $with->priceFor('wholesale'));
        $this->assertMoney(10, $with->priceFor('retail'));
        $this->assertMoney(10, $without->priceFor('wholesale'));
        $this->assertMoney(10, $zero->priceFor('wholesale'));
    }

    public function test_wholesale_customer_is_charged_wholesale_prices(): void
    {
        $p = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => 8]);
        $this->stock($p, 20);
        $c = $this->makeCustomer(['price_level' => 'wholesale']);

        $sale = $this->sell([$p->id => 3], ['customer_id' => $c->id]);

        $this->assertMoney(24, $sale->total);
        $this->assertSame('wholesale', $sale->price_level);
        $this->assertMoney(8, $sale->items()->first()->unit_price);
    }

    public function test_retail_customer_and_walk_in_pay_retail(): void
    {
        $p = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => 8]);
        $this->stock($p, 20);
        $c = $this->makeCustomer(['price_level' => 'retail']);

        $a = $this->sell([$p->id => 3], ['customer_id' => $c->id]);
        $b = $this->sell([$p->id => 3]);

        $this->assertMoney(30, $a->total);
        $this->assertMoney(30, $b->total);
        $this->assertSame('retail', $b->price_level);
    }

    public function test_wholesale_customer_falls_back_to_retail_when_no_wholesale_price(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 20);
        $c = $this->makeCustomer(['price_level' => 'wholesale']);

        $this->assertMoney(30, $this->sell([$p->id => 3], ['customer_id' => $c->id])->total);
    }

    public function test_wholesale_credit_sale_records_the_wholesale_amount_as_debt(): void
    {
        $p = $this->makeProduct(['retail_price' => 10, 'wholesale_price' => 8]);
        $this->stock($p, 20);
        $c = $this->makeCustomer(['price_level' => 'wholesale']);

        $sale = $this->sell([$p->id => 5], ['customer_id' => $c->id, 'payment_method' => 'credit']);

        $this->assertMoney(40, $sale->due_amount);
        $this->assertMoney(40, $c->fresh()->balance);
    }

    public function test_default_price_level_is_retail(): void
    {
        $this->assertSame('retail', $this->makeCustomer()->fresh()->price_level);
    }
}
