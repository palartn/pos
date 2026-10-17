<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Support\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * حساب العميل: كل دين أو دفعة يُسجَّل كحركة ويُحدَّث الرصيد في نفس العملية.
 */
class CustomerAccount
{
    /**
     * تسجيل دين جديد على العميل (من فاتورة آجلة).
     */
    public function addDebt(int $customerId, float $amount, ?Sale $sale, string $description): CustomerTransaction
    {
        return DB::transaction(function () use ($customerId, $amount, $sale, $description) {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('مبلغ الدين يجب أن يكون أكبر من صفر');
            }

            $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            $balance = round((float) $customer->balance + $amount, 2);

            $customer->update(['balance' => $balance]);

            return CustomerTransaction::create([
                'customer_id' => $customer->id,
                'sale_id' => $sale?->id,
                'cash_session_id' => $sale?->cash_session_id,
                'user_id' => Auth::id(),
                'type' => 'debt',
                'amount' => $amount,
                'balance_after' => $balance,
                'description' => $description,
            ]);
        });
    }

    /**
     * استلام دفعة من العميل وخصمها من رصيده.
     */
    public function receivePayment(
        int $customerId,
        float $amount,
        string $method,
        ?string $reference = null,
        ?string $note = null
    ): CustomerTransaction {
        return DB::transaction(function () use ($customerId, $amount, $method, $reference, $note) {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
            }

            if (! PaymentMethod::exists($method) || $method === 'credit') {
                throw new RuntimeException('طريقة الدفع غير صالحة');
            }

            $session = CashSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            // الدفعة النقدية تدخل الدرج، فيجب أن تُسجَّل ضمن جلسة كاشير مفتوحة
            if ($method === 'cash' && ! $session) {
                throw new RuntimeException('يجب فتح جلسة كاشير قبل استلام دفعة نقدية');
            }

            $customer = Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();

            if ($amount > (float) $customer->balance + 0.001) {
                throw new RuntimeException('المبلغ أكبر من الرصيد المستحق على العميل');
            }

            $balance = round((float) $customer->balance - $amount, 2);
            $customer->update(['balance' => $balance]);

            return CustomerTransaction::create([
                'customer_id' => $customer->id,
                'cash_session_id' => $session?->id,
                'user_id' => Auth::id(),
                'type' => 'payment',
                'amount' => $amount,
                'balance_after' => $balance,
                'payment_method' => $method,
                'reference' => $reference ?: null,
                'description' => $note ?: null,
            ]);
        });
    }
}
