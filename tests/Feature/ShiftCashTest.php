<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\SaleReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Builds;
use Tests\TestCase;

class ShiftCashTest extends TestCase
{
    use RefreshDatabase, Builds;

    private User $user;
    private CashierShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeUser();
        $this->actingAs($this->user);
        $this->shift = $this->openShift($this->user, 100);
    }

    private function scenario(): void
    {
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 100);
        $checkout = app(CheckoutService::class);
        $customer = $this->makeCustomer();
        $supplier = $this->makeSupplier();

        // 1) مبيعات نقدية 30
        $cashSale = $checkout->checkout([$p->id => 3], ['payment_method' => 'cash'], $this->shift, $this->user->id);
        // 2) مبيعات جوال باي 20 (ليست نقداً في الدرج)
        $checkout->checkout([$p->id => 2], ['payment_method' => 'jawwal_pay'], $this->shift, $this->user->id);
        // 3) بيع آجل 10 (دين، ليس نقداً)
        $checkout->checkout([$p->id => 1], ['payment_method' => 'credit', 'customer_id' => $customer->id], $this->shift, $this->user->id);

        // 4) العميل يدفع 4 نقداً و2 عبر بال باي
        $customer->addTransaction(LedgerType::Payment, 4, ['payment_method' => 'cash']);
        $customer->addTransaction(LedgerType::Payment, 2, ['payment_method' => 'palpay']);

        // 5) إيراد 7 ومصروف 12 من الدرج
        CashMovement::create(['shift_id' => $this->shift->id, 'user_id' => $this->user->id, 'type' => 'income', 'category' => 'other_income', 'amount' => 7, 'occurred_on' => today()]);
        CashMovement::create(['shift_id' => $this->shift->id, 'user_id' => $this->user->id, 'type' => 'expense', 'category' => 'supplies', 'amount' => 12, 'occurred_on' => today()]);

        // 6) دفعة نقدية للمورد 8
        $supplier->addTransaction(LedgerType::Debt, 20);
        $supplier->addTransaction(LedgerType::Payment, 8, ['payment_method' => 'cash']);

        // 7) مرتجع نقدي 10 من البيعة النقدية
        app(SaleReturnService::class)->process($cashSale, [$cashSale->items()->first()->id => 1]);
    }

    public function test_expected_cash_counts_only_real_cash_movements(): void
    {
        $this->scenario();

        $s = $this->shift->summary();

        // 100 + 30 (مبيعات نقدية) + 4 (دفعة عميل نقدية) + 7 (إيراد) − 8 (مورد) − 12 (مصروف) − 10 (مرتجع)
        $this->assertMoney(111, $s['expected_cash']);
        $this->assertMoney(30, $s['cash_sales']);
        $this->assertMoney(4, $s['customer_cash']);
        $this->assertMoney(8, $s['supplier_cash']);
        $this->assertMoney(10, $s['sale_refunds']);
        $this->assertSame(3, $s['sales_count']);
    }

    public function test_closing_stores_expected_counted_and_difference(): void
    {
        $this->scenario();

        $diff = $this->shift->closeWith(110);

        $this->assertMoney(-1, $diff);
        $shift = $this->shift->fresh();
        $this->assertSame('closed', $shift->status);
        $this->assertMoney(111, $shift->expected_cash);
        $this->assertMoney(110, $shift->counted_cash);
        $this->assertMoney(-1, $shift->difference);
        $this->assertNotNull($shift->closed_at);
    }

    public function test_exact_count_gives_zero_difference(): void
    {
        $this->scenario();

        $this->assertSame(0.0, (float) $this->shift->closeWith(111));
    }

    public function test_closing_twice_returns_null_and_keeps_first_result(): void
    {
        $this->scenario();
        $this->shift->closeWith(111);

        $this->assertNull($this->shift->closeWith(50));
        $this->assertMoney(111, $this->shift->fresh()->counted_cash);
    }

    public function test_open_shift_lookup_is_per_user_and_only_while_open(): void
    {
        $other = $this->makeUser();

        $this->assertEquals($this->shift->id, CashierShift::openFor($this->user->id)->id);
        $this->assertNull(CashierShift::openFor($other->id));

        $this->shift->closeWith(100);

        $this->assertNull(CashierShift::openFor($this->user->id));
    }
}
