<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\PaymentVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class PaymentVoucherTest extends TestCase
{
    use RefreshDatabase, Builds;

    private User $gm;
    private User $accountant;
    private User $cashier;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->gm = $this->makeUser('super_admin');
        $this->accountant = $this->makeUser('accountant');
        $this->cashier = $this->makeUser('cashier');

        $this->supplier = $this->makeSupplier();
        $this->supplier->addTransaction(LedgerType::Debt, 100, ['description' => 'فاتورة قديمة']);
    }

    private function svc(): PaymentVoucherService
    {
        return app(PaymentVoucherService::class);
    }

    public function test_payment_voucher_reduces_supplier_balance_and_records_everything(): void
    {
        $this->actingAs($this->accountant);

        $v = $this->svc()->pay($this->supplier, 40, 'cash', null, 'دفعة');

        $this->assertSame('PV-000001', $v->number);
        $this->assertMoney(40, $v->amount);
        $this->assertMoney(60, $v->balance_after);
        $this->assertMoney(60, $this->supplier->fresh()->balance);

        $tx = SupplierTransaction::find($v->supplier_transaction_id);
        $this->assertSame(LedgerType::Payment, $tx->type);
        $this->assertSame('cash', $tx->payment_method);
        $this->assertStringContainsString('PV-000001', $tx->description);
    }

    public function test_numbers_are_sequential_and_independent_from_receipts(): void
    {
        $this->actingAs($this->accountant);

        $this->assertSame('PV-000001', $this->svc()->pay($this->supplier, 10)->number);
        $this->assertSame('PV-000002', $this->svc()->pay($this->supplier, 10)->number);
    }

    public function test_overpayment_is_rejected_and_the_number_is_not_burned(): void
    {
        $this->actingAs($this->accountant);

        try {
            $this->svc()->pay($this->supplier, 150);
            $this->fail('كان يجب رفض مبلغ أكبر من المستحق');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('أكبر من الرصيد', $e->getMessage());
        }

        $this->assertSame(0, PaymentVoucher::count());
        $this->assertMoney(100, $this->supplier->fresh()->balance);
        $this->assertSame('PV-000001', $this->svc()->pay($this->supplier, 10)->number);
    }

    public function test_cashier_cannot_pay_suppliers(): void
    {
        $this->actingAs($this->cashier);

        $this->expectException(RuntimeException::class);
        $this->svc()->pay($this->supplier, 10);
    }

    public function test_only_the_general_manager_can_cancel(): void
    {
        $this->actingAs($this->accountant);
        $v = $this->svc()->pay($this->supplier, 40, 'palpay');

        try {
            $this->svc()->cancel($v, 'محاولة');
            $this->fail('كان يجب رفض الإلغاء لغير المدير العام');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('غير مصرح', $e->getMessage());
        }

        $this->actingAs($this->gm);
        $this->svc()->cancel($v, 'خطأ في المبلغ');

        $v = $v->fresh();
        $this->assertSame('cancelled', $v->status);
        $this->assertEquals($this->gm->id, $v->cancelled_by);
        $this->assertMoney(100, $this->supplier->fresh()->balance);   // عاد المستحق للمورد
        $this->assertSame('PV-000001', $v->number);
    }

    public function test_cancel_needs_reason_and_cannot_repeat(): void
    {
        $this->actingAs($this->gm);
        $v = $this->svc()->pay($this->supplier, 40, 'palpay');

        try {
            $this->svc()->cancel($v, '');
            $this->fail('كان يجب طلب السبب');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('سبب', $e->getMessage());
        }

        $this->svc()->cancel($v, 'خطأ');

        $this->expectException(RuntimeException::class);
        $this->svc()->cancel($v, 'مكرر');
    }

    public function test_cancelling_a_cash_payment_needs_an_open_shift(): void
    {
        $this->actingAs($this->gm);
        $v = $this->svc()->pay($this->supplier, 40, 'cash');

        try {
            $this->svc()->cancel($v, 'خطأ');
            $this->fail('كان يجب طلب وردية مفتوحة');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('وردية', $e->getMessage());
        }

        $this->assertSame('active', $v->fresh()->status);
    }

    public function test_cash_payment_and_its_cancellation_move_the_drawer_balance(): void
    {
        $this->actingAs($this->gm);
        $shift = $this->openShift($this->gm, 100);

        $v = $this->svc()->pay($this->supplier, 40, 'cash');
        $this->assertMoney(60, $shift->summary()['expected_cash']);        // خرج 40 من الدرج

        $this->svc()->cancel($v, 'خطأ');

        $s = $shift->summary();
        $this->assertMoney(40, $s['supplier_cash']);
        $this->assertMoney(40, $s['supplier_cash_reversals']);
        $this->assertMoney(100, $s['expected_cash']);                      // عاد المبلغ إلى الدرج
    }

    public function test_print_access(): void
    {
        $this->actingAs($this->accountant);
        $v = $this->svc()->pay($this->supplier, 10);
        auth()->logout();

        $this->get(route('payment-vouchers.show', $v))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('payment-vouchers.show', $v))->assertOk()->assertSee($v->number);
        $this->actingAs($this->gm)->get(route('payment-vouchers.show', $v))->assertOk();
        $this->actingAs($this->cashier)->get(route('payment-vouchers.show', $v))->assertForbidden();
    }

    public function test_cancel_permission_belongs_to_the_general_manager_only(): void
    {
        $this->assertTrue($this->gm->can('payvouchers.cancel'));
        $this->assertFalse($this->accountant->can('payvouchers.cancel'));
        $this->assertFalse($this->makeUser('branch_manager')->can('payvouchers.cancel'));
        $this->assertTrue($this->accountant->can('payvouchers.create'));
        $this->assertFalse($this->cashier->can('payvouchers.create'));
    }
}
