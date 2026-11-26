<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** سندات الصرف: دفعات الموردين بترقيم تسلسلي وإلغاء محكوم بالصلاحيات */
class PaymentVoucherService
{
    private function authorize(string $permission): void
    {
        $user = auth()->user();

        if (! $user || ! $user->can($permission)) {
            throw new RuntimeException('غير مصرح لك بهذه العملية');
        }
    }

    public function pay(
        Supplier $supplier,
        float $amount,
        string $method = 'cash',
        ?string $details = null,
        ?string $notes = null,
    ): PaymentVoucher {
        $this->authorize('payvouchers.create');

        $pm = PaymentMethod::tryFrom($method);

        if (! $pm || $pm === PaymentMethod::Credit) {
            throw new RuntimeException('طريقة الدفع غير صالحة لسند صرف');
        }

        return DB::transaction(function () use ($supplier, $amount, $pm, $details, $notes) {
            $number = app(NumberSequence::class)->next('payment_voucher');

            $tx = $supplier->addTransaction(LedgerType::Payment, $amount, [
                'description' => 'سند صرف ' . $number . ($notes ? ' - ' . $notes : ''),
                'payment_method' => $pm->value,
                'payment_details' => $pm->needsDetails() ? ($details ?: null) : null,
            ]);

            return PaymentVoucher::create([
                'number' => $number,
                'supplier_id' => $supplier->id,
                'amount' => $tx->amount,
                'payment_method' => $pm->value,
                'payment_details' => $pm->needsDetails() ? ($details ?: null) : null,
                'notes' => $notes,
                'balance_after' => $tx->balance_after,
                'shift_id' => $tx->shift_id,
                'user_id' => auth()->id(),
                'supplier_transaction_id' => $tx->id,
            ]);
        });
    }

    /** إلغاء سند صرف (صلاحية payvouchers.cancel للمدير العام): يعود المستحق للمورد ويعود النقد إلى الدرج */
    public function cancel(PaymentVoucher $voucher, string $reason): PaymentVoucher
    {
        $this->authorize('payvouchers.cancel');

        if (trim($reason) === '') {
            throw new RuntimeException('سبب الإلغاء مطلوب');
        }

        return DB::transaction(function () use ($voucher, $reason) {
            $v = PaymentVoucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($v->isCancelled()) {
                throw new RuntimeException('السند ملغى مسبقاً');
            }

            if ($v->payment_method === 'cash' && ! CashierShift::openFor(auth()->id())) {
                throw new RuntimeException('افتح وردية أولاً: إلغاء سند صرف نقدي يُعيد المبلغ إلى الدرج');
            }

            $supplier = Supplier::findOrFail($v->supplier_id);

            $supplier->addTransaction(LedgerType::Debt, (float) $v->amount, [
                'description' => "إلغاء سند صرف {$v->number} - {$reason}",
                'payment_method' => $v->payment_method,
            ]);

            $v->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);

            return $v;
        });
    }
}
