<?php

namespace App\Services;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** سندات القبض: استلام دفعات العملاء بترقيم تسلسلي وإلغاء محكوم بالصلاحيات */
class VoucherService
{
    private function authorize(string $permission): void
    {
        $user = auth()->user();

        if (! $user || ! $user->can($permission)) {
            throw new RuntimeException('غير مصرح لك بهذه العملية');
        }
    }

    /** إصدار سند قبض: يخصم المبلغ من دين العميل ويسجّله في كشف حسابه ووردية الكاشير المفتوحة */
    public function receive(
        Customer $customer,
        float $amount,
        string $method = 'cash',
        ?string $details = null,
        ?string $notes = null,
    ): Voucher {
        $this->authorize('vouchers.create');

        $pm = PaymentMethod::tryFrom($method);

        if (! $pm || $pm === PaymentMethod::Credit) {
            throw new RuntimeException('طريقة الدفع غير صالحة لسند قبض');
        }

        return DB::transaction(function () use ($customer, $amount, $pm, $details, $notes) {
            $number = app(NumberSequence::class)->next('receipt_voucher');

            $tx = $customer->addTransaction(LedgerType::Payment, $amount, [
                'description' => 'سند قبض ' . $number . ($notes ? ' - ' . $notes : ''),
                'payment_method' => $pm->value,
                'payment_details' => $pm->needsDetails() ? ($details ?: null) : null,
            ]);

            return Voucher::create([
                'number' => $number,
                'type' => 'receipt',
                'customer_id' => $customer->id,
                'amount' => $tx->amount,
                'payment_method' => $pm->value,
                'payment_details' => $pm->needsDetails() ? ($details ?: null) : null,
                'notes' => $notes,
                'balance_after' => $tx->balance_after,
                'shift_id' => $tx->shift_id,
                'user_id' => auth()->id(),
                'customer_transaction_id' => $tx->id,
            ]);
        });
    }

    /** إلغاء سند (صلاحية vouchers.cancel للمدير العام): يبقى رقمه ظاهراً ويعود الدين على العميل */
    public function cancel(Voucher $voucher, string $reason): Voucher
    {
        $this->authorize('vouchers.cancel');

        if (trim($reason) === '') {
            throw new RuntimeException('سبب الإلغاء مطلوب');
        }

        return DB::transaction(function () use ($voucher, $reason) {
            $v = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($v->isCancelled()) {
                throw new RuntimeException('السند ملغى مسبقاً');
            }

            // سند نقدي: يخرج المبلغ من درج الوردية الحالية، فلا بد من وردية مفتوحة
            if ($v->payment_method === 'cash' && ! CashierShift::openFor(auth()->id())) {
                throw new RuntimeException('افتح وردية أولاً: إلغاء سند نقدي يُخرج المبلغ من الدرج');
            }

            $customer = Customer::findOrFail($v->customer_id);

            $customer->addTransaction(LedgerType::Debt, (float) $v->amount, [
                'description' => "إلغاء سند قبض {$v->number} - {$reason}",
                'payment_method' => $v->payment_method,   // يُخصم من نقد الوردية إن كان نقدياً
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
