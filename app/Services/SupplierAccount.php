<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Support\PaymentMethod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * حساب المورد: الرصيد الموجب = للمورد علينا، والسالب = لنا عند المورد.
 * كل حركة تُسجَّل في كشف الحساب ويُحدَّث الرصيد في نفس العملية.
 */
class SupplierAccount
{
    protected function lockSupplier(int $supplierId): Supplier
    {
        return Supplier::whereKey($supplierId)->lockForUpdate()->firstOrFail();
    }

    /**
     * تسجيل حركة وتحديث الرصيد. $delta موقّع: موجب يزيد ما علينا.
     */
    protected function record(Supplier $supplier, float $delta, array $attributes): SupplierTransaction
    {
        $balance = round((float) $supplier->balance + $delta, 2);

        $supplier->update(['balance' => $balance]);

        return SupplierTransaction::create(array_merge($attributes, [
            'supplier_id' => $supplier->id,
            'user_id' => Auth::id(),
            'amount' => $delta,
            'balance_after' => $balance,
        ]));
    }

    /**
     * رصيد سابق عند إضافة المورد. موجب = علينا للمورد، سالب = لنا عنده.
     */
    public function addOpening(int $supplierId, float $amount, ?string $note = null): SupplierTransaction
    {
        return DB::transaction(function () use ($supplierId, $amount, $note) {
            $amount = round($amount, 2);

            if ($amount == 0.0) {
                throw new RuntimeException('الرصيد السابق يجب ألا يكون صفراً');
            }

            return $this->record($this->lockSupplier($supplierId), $amount, [
                'type' => 'opening',
                'description' => $note ?: 'رصيد سابق',
            ]);
        });
    }

    /**
     * فاتورة شراء: تزيد ما علينا للمورد بإجمالي الفاتورة.
     */
    public function addPurchase(int $supplierId, float $amount, Purchase $purchase, string $description): SupplierTransaction
    {
        return DB::transaction(function () use ($supplierId, $amount, $purchase, $description) {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('إجمالي الفاتورة يجب أن يكون أكبر من صفر');
            }

            return $this->record($this->lockSupplier($supplierId), $amount, [
                'purchase_id' => $purchase->id,
                'cash_session_id' => null,
                'type' => 'purchase',
                'description' => $description,
            ]);
        });
    }

    /**
     * دفعة للمورد. تُنقص ما علينا، ويجوز أن تتجاوز الرصيد (تصبح دفعة مقدمة).
     * الدفعة النقدية "من الدرج" تتطلب جلسة كاشير مفتوحة وتُخصم من النقد المتوقع.
     */
    public function pay(
        int $supplierId,
        float $amount,
        string $method,
        ?string $reference = null,
        ?string $note = null,
        bool $fromDrawer = true,
        ?Purchase $purchase = null
    ): SupplierTransaction {
        return DB::transaction(function () use ($supplierId, $amount, $method, $reference, $note, $fromDrawer, $purchase) {
            $amount = round($amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException('المبلغ يجب أن يكون أكبر من صفر');
            }

            if (! PaymentMethod::exists($method) || $method === 'credit') {
                throw new RuntimeException('طريقة الدفع غير صالحة');
            }

            $reference = trim((string) $reference);

            if (PaymentMethod::detailsRequired($method) && $reference === '') {
                throw new RuntimeException('حدّد طريقة الدفع');
            }

            $session = CashSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if ($method === 'cash' && $fromDrawer && ! $session) {
                throw new RuntimeException('يجب فتح جلسة كاشير لخصم الدفعة النقدية من الدرج');
            }

            // الدفعة النقدية من خارج الدرج لا تُربط بالجلسة فلا تؤثر على النقد المتوقع
            $sessionId = ($method === 'cash' && ! $fromDrawer) ? null : $session?->id;

            return $this->record($this->lockSupplier($supplierId), -$amount, [
                'purchase_id' => $purchase?->id,
                'cash_session_id' => $sessionId,
                'type' => 'payment',
                'payment_method' => $method,
                'reference' => $reference !== '' ? $reference : null,
                'description' => $note ?: null,
            ]);
        });
    }
}
