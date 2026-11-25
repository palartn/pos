<?php

namespace Tests\Feature;

use App\Enums\EmployeeTransactionType;
use App\Models\CashMovement;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase, Builds;

    private function employee(float $salary = 1000): Employee
    {
        return Employee::create(['name' => 'موظف ' . uniqid(), 'base_salary' => $salary]);
    }

    public function test_net_salary_is_base_plus_bonus_minus_deduction_minus_advance(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $e = $this->employee(1000);
        $svc = app(PayrollService::class);

        $svc->addTransaction($e, EmployeeTransactionType::Advance, 200, 'سلفة', $shift->id, '2026-10-05');
        $svc->addTransaction($e, EmployeeTransactionType::Bonus, 50, null, null, '2026-10-10');
        $svc->addTransaction($e, EmployeeTransactionType::Deduction, 30, 'غياب', null, '2026-10-12');

        $this->assertMoney(820, $svc->preview($e, '2026-10')['net']);

        // السلفة خرجت نقداً من الدرج؛ المكافأة والخصم لا يحركان النقد
        $this->assertSame(1, CashMovement::count());
        $adv = CashMovement::first();
        $this->assertSame('employee_advance', $adv->category);
        $this->assertMoney(200, $adv->amount);
        $this->assertEquals($shift->id, $adv->shift_id);

        $payroll = $svc->pay($e, '2026-10');

        $this->assertMoney(820, $payroll->net_pay);
        $this->assertMoney(1000, $payroll->base_salary);
        $this->assertMoney(200, $payroll->advances);

        $salary = CashMovement::where('category', 'salaries')->first();
        $this->assertMoney(820, $salary->amount);
        $this->assertSame('expense', $salary->type->value);

        // كل الحركات سُوّيت
        $this->assertSame(0, $e->transactions()->whereNull('payroll_id')->count());
    }

    public function test_salary_cannot_be_paid_twice_for_the_same_month(): void
    {
        $e = $this->employee(500);
        $svc = app(PayrollService::class);
        $svc->pay($e, '2026-10');

        try {
            $svc->pay($e, '2026-10');
            $this->fail('كان يجب رفض صرف الراتب مرتين');
        } catch (RuntimeException $ex) {
            $this->assertStringContainsString('مسبقاً', $ex->getMessage());
        }

        $this->assertSame(1, Payroll::count());
        $this->assertSame(1, CashMovement::where('category', 'salaries')->count());
    }

    public function test_negative_net_is_rejected_and_nothing_is_recorded(): void
    {
        $e = $this->employee(100);
        $svc = app(PayrollService::class);
        $svc->addTransaction($e, EmployeeTransactionType::Advance, 300, null, null, '2026-10-05');

        $this->expectException(RuntimeException::class);

        try {
            $svc->pay($e, '2026-10');
        } finally {
            $this->assertSame(0, Payroll::count());
            $this->assertSame(0, CashMovement::where('category', 'salaries')->count());
            $this->assertSame(1, $e->transactions()->whereNull('payroll_id')->count());
        }
    }

    public function test_zero_net_creates_payroll_without_cash_movement(): void
    {
        $e = $this->employee(200);
        $svc = app(PayrollService::class);
        $svc->addTransaction($e, EmployeeTransactionType::Advance, 200, null, null, '2026-10-05');

        $payroll = $svc->pay($e, '2026-10');

        $this->assertMoney(0, $payroll->net_pay);
        $this->assertNull($payroll->cash_movement_id);
        $this->assertSame(0, CashMovement::where('category', 'salaries')->count());
    }

    public function test_transactions_after_the_period_are_not_included(): void
    {
        $e = $this->employee(1000);
        $svc = app(PayrollService::class);
        $svc->addTransaction($e, EmployeeTransactionType::Deduction, 100, null, null, '2026-11-05');

        $this->assertMoney(1000, $svc->preview($e, '2026-10')['net']);
        $this->assertMoney(900, $svc->preview($e, '2026-11')['net']);
    }

    public function test_advance_from_a_closed_shift_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $shift->closeWith(0);
        $e = $this->employee();

        $this->expectException(RuntimeException::class);
        app(PayrollService::class)->addTransaction($e, EmployeeTransactionType::Advance, 50, null, $shift->id);
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        app(PayrollService::class)->addTransaction($this->employee(), EmployeeTransactionType::Bonus, 0);
    }
}
