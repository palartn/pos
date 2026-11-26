<?php

namespace Tests\Feature;

use App\Enums\LedgerType;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase, Builds;

    private User $gm;
    private User $manager;
    private User $cashier;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->gm = $this->makeUser('super_admin');
        $this->manager = $this->makeUser('branch_manager');
        $this->cashier = $this->makeUser('cashier');

        $this->customer = $this->makeCustomer();
        $this->customer->addTransaction(LedgerType::Debt, 100, ['description' => 'دين سابق']);
    }

    private function svc(): VoucherService
    {
        return app(VoucherService::class);
    }

    public function test_receipt_voucher_reduces_debt_and_records_everything(): void
    {
        $this->actingAs($this->manager);

        $v = $this->svc()->receive($this->customer, 40, 'cash', null, 'دفعة أولى');

        $this->assertSame('RV-000001', $v->number);
        $this->assertMoney(40, $v->amount);
        $this->assertMoney(60, $v->balance_after);
        $this->assertMoney(60, $this->customer->fresh()->balance);
        $this->assertSame('active', $v->status);
        $this->assertEquals($this->manager->id, $v->user_id);

        $tx = CustomerTransaction::find($v->customer_transaction_id);
        $this->assertSame(LedgerType::Payment, $tx->type);
        $this->assertSame('cash', $tx->payment_method);
        $this->assertStringContainsString('RV-000001', $tx->description);
    }

    public function test_voucher_numbers_are_sequential(): void
    {
        $this->actingAs($this->manager);

        $a = $this->svc()->receive($this->customer, 10);
        $b = $this->svc()->receive($this->customer, 10);

        $this->assertSame('RV-000001', $a->number);
        $this->assertSame('RV-000002', $b->number);
    }

    public function test_overpayment_is_rejected_and_the_number_is_not_burned(): void
    {
        $this->actingAs($this->manager);

        try {
            $this->svc()->receive($this->customer, 150);
            $this->fail('كان يجب رفض مبلغ أكبر من الدين');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('أكبر من الرصيد', $e->getMessage());
        }

        $this->assertSame(0, Voucher::count());
        $this->assertMoney(100, $this->customer->fresh()->balance);
        $this->assertSame('RV-000001', $this->svc()->receive($this->customer, 10)->number);
    }

    public function test_non_cash_method_keeps_its_details(): void
    {
        $this->actingAs($this->manager);

        $v = $this->svc()->receive($this->customer, 25, 'jawwal_pay', 'مرجع 8841');

        $this->assertSame('jawwal_pay', $v->payment_method);
        $this->assertSame('مرجع 8841', $v->payment_details);
    }

    public function test_credit_is_not_a_valid_receipt_method(): void
    {
        $this->actingAs($this->manager);

        $this->expectException(RuntimeException::class);
        $this->svc()->receive($this->customer, 10, 'credit');
    }

    public function test_user_without_permission_cannot_issue_a_voucher(): void
    {
        $this->actingAs($this->makeUser('storekeeper'));

        try {
            $this->svc()->receive($this->customer, 10);
            $this->fail('كان يجب رفض المستخدم بدون صلاحية');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('غير مصرح', $e->getMessage());
        }

        $this->assertSame(0, Voucher::count());
    }

    public function test_only_the_general_manager_can_cancel_a_voucher(): void
    {
        $this->actingAs($this->manager);
        $v = $this->svc()->receive($this->customer, 40, 'jawwal_pay');

        foreach ([$this->manager, $this->cashier] as $user) {
            $this->actingAs($user);

            try {
                $this->svc()->cancel($v, 'محاولة');
                $this->fail('كان يجب رفض الإلغاء لغير المدير العام');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('غير مصرح', $e->getMessage());
            }
        }

        $this->assertSame('active', $v->fresh()->status);
        $this->assertMoney(60, $this->customer->fresh()->balance);

        $this->actingAs($this->gm);
        $this->svc()->cancel($v, 'خطأ في الإدخال');

        $v = $v->fresh();
        $this->assertSame('cancelled', $v->status);
        $this->assertEquals($this->gm->id, $v->cancelled_by);
        $this->assertSame('خطأ في الإدخال', $v->cancel_reason);
        $this->assertMoney(100, $this->customer->fresh()->balance);   // عاد الدين
        $this->assertSame('RV-000001', $v->number);                   // الرقم يبقى ظاهراً
    }

    public function test_cancel_requires_a_reason_and_cannot_repeat(): void
    {
        $this->actingAs($this->gm);
        $v = $this->svc()->receive($this->customer, 40, 'palpay');

        try {
            $this->svc()->cancel($v, '   ');
            $this->fail('كان يجب طلب سبب الإلغاء');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('سبب', $e->getMessage());
        }

        $this->svc()->cancel($v, 'خطأ');

        try {
            $this->svc()->cancel($v, 'مرة ثانية');
            $this->fail('كان يجب رفض الإلغاء المكرر');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('مسبقاً', $e->getMessage());
        }

        $this->assertMoney(100, $this->customer->fresh()->balance);   // لم يُضاف الدين مرتين
    }

    public function test_cancelling_a_cash_voucher_needs_an_open_shift(): void
    {
        $this->actingAs($this->gm);
        $v = $this->svc()->receive($this->customer, 40, 'cash');

        try {
            $this->svc()->cancel($v, 'خطأ');
            $this->fail('كان يجب طلب وردية مفتوحة');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('وردية', $e->getMessage());
        }

        $this->assertSame('active', $v->fresh()->status);
        $this->assertMoney(60, $this->customer->fresh()->balance);
    }

    public function test_cancelled_cash_voucher_leaves_the_drawer_in_the_shift_summary(): void
    {
        $this->actingAs($this->gm);
        $shift = $this->openShift($this->gm, 100);

        $v = $this->svc()->receive($this->customer, 40, 'cash');
        $this->assertMoney(140, $shift->summary()['expected_cash']);

        $this->svc()->cancel($v, 'خطأ');

        $s = $shift->summary();
        $this->assertMoney(40, $s['customer_cash']);
        $this->assertMoney(40, $s['customer_cash_reversals']);
        $this->assertMoney(100, $s['expected_cash']);
    }

    public function test_voucher_print_access(): void
    {
        $this->actingAs($this->cashier);
        $v = $this->svc()->receive($this->customer, 10);
        auth()->logout();

        $this->get(route('vouchers.show', $v))->assertForbidden();                                         // زائر
        $this->actingAs($this->cashier)->get(route('vouchers.show', $v))->assertOk()->assertSee($v->number); // من أصدره
        $this->actingAs($this->makeUser('cashier'))->get(route('vouchers.show', $v))->assertForbidden();     // كاشير آخر
        $this->actingAs($this->manager)->get(route('vouchers.show', $v))->assertOk();                        // يملك vouchers.view
        $this->actingAs($this->makeUser('storekeeper'))->get(route('vouchers.show', $v))->assertForbidden();
    }

    public function test_cancel_and_sequence_permissions_belong_to_the_general_manager_only(): void
    {
        foreach (['vouchers.cancel', 'sequences.manage'] as $permission) {
            $this->assertTrue($this->gm->can($permission));
            $this->assertFalse($this->manager->can($permission));
            $this->assertFalse($this->cashier->can($permission));
            $this->assertFalse($this->makeUser('accountant')->can($permission));
        }

        $this->assertTrue($this->cashier->can('vouchers.create'));
        $this->assertFalse($this->cashier->can('vouchers.view'));
    }
}
