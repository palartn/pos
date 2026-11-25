<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\CustomerTransaction;
use App\Models\SupplierTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase, Builds;

    public function test_debt_and_payment_update_balance_and_statement(): void
    {
        $c = $this->makeCustomer();

        $c->addTransaction(LedgerType::Debt, 100, ['description' => 'بضاعة']);
        $c->addTransaction(LedgerType::Payment, 30, ['payment_method' => 'cash']);

        $this->assertMoney(70, $c->fresh()->balance);

        $txs = CustomerTransaction::where('customer_id', $c->id)->orderBy('id')->get();
        $this->assertCount(2, $txs);
        $this->assertSame(LedgerType::Debt, $txs[0]->type);
        $this->assertMoney(100, $txs[0]->balance_after);
        $this->assertMoney(70, $txs[1]->balance_after);
    }

    public function test_payment_cannot_exceed_balance(): void
    {
        $c = $this->makeCustomer();
        $c->addTransaction(LedgerType::Debt, 50);

        try {
            $c->addTransaction(LedgerType::Payment, 60);
            $this->fail('كان يجب رفض دفعة أكبر من الدين');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('أكبر من الرصيد', $e->getMessage());
        }

        $this->assertMoney(50, $c->fresh()->balance);
        $this->assertSame(1, CustomerTransaction::where('customer_id', $c->id)->count());
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $c = $this->makeCustomer();

        $this->expectException(InvalidArgumentException::class);
        $c->addTransaction(LedgerType::Debt, 0);
    }

    public function test_balance_always_equals_last_balance_after(): void
    {
        $s = $this->makeSupplier();

        foreach ([[LedgerType::Debt, 100], [LedgerType::Payment, 30], [LedgerType::Debt, 20], [LedgerType::Payment, 40]] as [$type, $amount]) {
            $s->addTransaction($type, $amount);
        }

        $balances = SupplierTransaction::where('supplier_id', $s->id)->orderBy('id')->pluck('balance_after')->map(fn ($v) => (float) $v)->all();

        $this->assertSame([100.0, 70.0, 90.0, 50.0], $balances);
        $this->assertMoney(50, $s->fresh()->balance);
    }

    public function test_small_decimals_do_not_drift(): void
    {
        $c = $this->makeCustomer();
        $c->addTransaction(LedgerType::Debt, 0.1);
        $c->addTransaction(LedgerType::Debt, 0.2);

        $this->assertSame('0.30', (string) $c->fresh()->balance);
    }

    public function test_transaction_is_attached_to_open_shift_of_current_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $c = $this->makeCustomer();

        $tx = $c->addTransaction(LedgerType::Debt, 10);

        $this->assertEquals($shift->id, $tx->shift_id);
    }

    public function test_transaction_without_open_shift_has_no_shift(): void
    {
        $this->actingAs($this->makeUser());
        $c = $this->makeCustomer();

        $this->assertNull($c->addTransaction(LedgerType::Debt, 10)->shift_id);
    }
}
