<?php

namespace App\Services;

use App\Enums\EmployeeTransactionType;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    /** قائمة آخر 12 شهراً بصيغة YYYY-MM */
    public static function periodOptions(): array
    {
        $out = [];

        for ($i = 0; $i < 12; $i++) {
            $p = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $out[$p] = $p;
        }

        return $out;
    }

    private function checkShift(?int $shiftId): ?int
    {
        if ($shiftId && ! CashierShift::whereKey($shiftId)->where('status', 'open')->exists()) {
            throw new RuntimeException('وردية الكاشير المختارة مغلقة، اختر درجاً آخر أو الخزينة الرئيسية');
        }

        return $shiftId ?: null;
    }

    /** تسجيل سلفة / مكافأة / خصم. السلفة يخرج مبلغها نقداً فتُسجَّل كمصروف */
    public function addTransaction(
        Employee $employee,
        EmployeeTransactionType $type,
        float $amount,
        ?string $description = null,
        ?int $shiftId = null,
        ?string $date = null,
    ): EmployeeTransaction {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
        }

        return DB::transaction(function () use ($employee, $type, $amount, $description, $shiftId, $date) {
            $date = $date ?: today()->toDateString();
            $movementId = null;

            if ($type === EmployeeTransactionType::Advance) {
                $movementId = CashMovement::create([
                    'shift_id' => $this->checkShift($shiftId),
                    'user_id' => auth()->id(),
                    'type' => 'expense',
                    'category' => 'employee_advance',
                    'amount' => $amount,
                    'description' => 'سلفة للموظف ' . $employee->name . ($description ? " - {$description}" : ''),
                    'occurred_on' => $date,
                ])->id;
            }

            return $employee->transactions()->create([
                'type' => $type,
                'amount' => $amount,
                'occurred_on' => $date,
                'description' => $description,
                'cash_movement_id' => $movementId,
                'user_id' => auth()->id(),
            ]);
        });
    }

    /** معاينة الراتب الصافي لشهر معيّن (الحركات غير المسوّاة حتى نهاية الشهر) */
    public function preview(Employee $employee, string $period): array
    {
        $end = Carbon::createFromFormat('!Y-m', $period)->endOfMonth()->toDateString();

        $tx = $employee->transactions()->whereNull('payroll_id')->whereDate('occurred_on', '<=', $end)->get();

        $sum = fn (EmployeeTransactionType $t) => round((float) $tx->where('type', $t)->sum('amount'), 2);

        $base = (float) $employee->base_salary;
        $bonuses = $sum(EmployeeTransactionType::Bonus);
        $deductions = $sum(EmployeeTransactionType::Deduction);
        $advances = $sum(EmployeeTransactionType::Advance);

        return [
            'base' => $base,
            'bonuses' => $bonuses,
            'deductions' => $deductions,
            'advances' => $advances,
            'net' => round($base + $bonuses - $deductions - $advances, 2),
            'ids' => $tx->pluck('id')->all(),
        ];
    }

    public function pay(Employee $employee, string $period, ?int $shiftId = null, ?string $notes = null): Payroll
    {
        return DB::transaction(function () use ($employee, $period, $shiftId, $notes) {
            $emp = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (Payroll::where('employee_id', $emp->id)->where('period', $period)->exists()) {
                throw new RuntimeException("تم صرف راتب {$emp->name} عن {$period} مسبقاً");
            }

            $p = $this->preview($emp, $period);

            if ($p['net'] < 0) {
                throw new RuntimeException("صافي راتب {$emp->name} سالب: السلف والخصومات تتجاوز الراتب. عدّل الحركات أو أضف مكافأة");
            }

            $movementId = null;

            if ($p['net'] > 0) {
                $movementId = CashMovement::create([
                    'shift_id' => $this->checkShift($shiftId),
                    'user_id' => auth()->id(),
                    'type' => 'expense',
                    'category' => 'salaries',
                    'amount' => $p['net'],
                    'description' => "راتب {$emp->name} عن شهر {$period}",
                    'occurred_on' => today()->toDateString(),
                ])->id;
            }

            $payroll = Payroll::create([
                'employee_id' => $emp->id,
                'period' => $period,
                'base_salary' => $p['base'],
                'bonuses' => $p['bonuses'],
                'deductions' => $p['deductions'],
                'advances' => $p['advances'],
                'net_pay' => $p['net'],
                'cash_movement_id' => $movementId,
                'paid_on' => today()->toDateString(),
                'user_id' => auth()->id(),
                'notes' => $notes,
            ]);

            EmployeeTransaction::whereIn('id', $p['ids'])->update(['payroll_id' => $payroll->id]);

            return $payroll;
        });
    }
}
