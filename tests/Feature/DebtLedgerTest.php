<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\CashierShift;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DebtLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Builds;
use Tests\TestCase;

class DebtLedgerTest extends TestCase
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

    private function from()
    {
        return now()->subDay();
    }

    private function until()
    {
        return now()->addDay();
    }

    public function test_entries_list_debts_and_payments_with_invoice_items(): void
    {
        $milk = $this->makeProduct(['name' => 'حليب', 'retail_price' => 5]);
        $this->stock($milk, 50);
        $c = $this->makeCustomer(['name' => 'أحمد']);

        app(CheckoutService::class)->checkout([$milk->id => 4], ['payment_method' => 'credit', 'customer_id' => $c->id], $this->shift, $this->user->id);
        $c->addTransaction(LedgerType::Payment, 8, ['payment_method' => 'cash', 'description' => 'دفعة']);

        $entries = app(DebtLedgerService::class)->entries($this->from(), $this->until());

        $this->assertCount(2, $entries);
        $this->assertSame('أحمد', $entries[0]['customer']);
        $this->assertSame(LedgerType::Debt, $entries[0]['type']);
        $this->assertMoney(20, $entries[0]['amount']);
        $this->assertStringContainsString('حليب × 4', $entries[0]['items']);
        $this->assertMoney(20, $entries[0]['balance_after']);

        $this->assertSame(LedgerType::Payment, $entries[1]['type']);
        $this->assertMoney(12, $entries[1]['balance_after']);
        $this->assertSame('', $entries[1]['items']);
    }

    public function test_filters_by_customer_type_and_period(): void
    {
        $a = $this->makeCustomer(['name' => 'أ']);
        $b = $this->makeCustomer(['name' => 'ب']);
        $a->addTransaction(LedgerType::Debt, 100);
        $a->addTransaction(LedgerType::Payment, 30);
        $b->addTransaction(LedgerType::Debt, 50);
        $svc = app(DebtLedgerService::class);

        $this->assertCount(3, $svc->entries($this->from(), $this->until()));
        $this->assertCount(2, $svc->entries($this->from(), $this->until(), $a->id));
        $this->assertCount(1, $svc->entries($this->from(), $this->until(), $a->id, 'payment'));
        $this->assertCount(2, $svc->entries($this->from(), $this->until(), null, 'debt'));
        $this->assertCount(0, $svc->entries(now()->addDays(5), now()->addDays(6)));
    }

    public function test_summary_numbers(): void
    {
        $a = $this->makeCustomer();
        $b = $this->makeCustomer();
        $a->addTransaction(LedgerType::Debt, 100);
        $a->addTransaction(LedgerType::Payment, 30);
        $b->addTransaction(LedgerType::Debt, 50);

        $s = app(DebtLedgerService::class)->summary($this->from(), $this->until());

        $this->assertMoney(150, $s['debts']);
        $this->assertMoney(30, $s['payments']);
        $this->assertMoney(120, $s['net']);
        $this->assertMoney(120, $s['outstanding']);     // 70 + 50
        $this->assertSame(2, $s['debtors']);
    }

    public function test_per_customer_summary_is_sorted_by_current_debt(): void
    {
        $small = $this->makeCustomer(['name' => 'صغير']);
        $big = $this->makeCustomer(['name' => 'كبير']);
        $small->addTransaction(LedgerType::Debt, 20);
        $big->addTransaction(LedgerType::Debt, 200);
        $big->addTransaction(LedgerType::Payment, 50);

        $rows = app(DebtLedgerService::class)->perCustomer($this->from(), $this->until());

        $this->assertSame('كبير', $rows[0]['customer']);
        $this->assertMoney(200, $rows[0]['debts']);
        $this->assertMoney(50, $rows[0]['payments']);
        $this->assertMoney(150, $rows[0]['balance']);
        $this->assertSame('صغير', $rows[1]['customer']);
    }
}
