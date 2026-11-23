<?php

namespace App\Models\Concerns;

use App\Enums\LedgerType;
use App\Models\CashierShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * دفتر حساب (دين/دفعة) للعملاء والموردين.
 * يتطلب: عمود balance وعلاقة transactions().
 */
trait HasLedger
{
    public function addTransaction(LedgerType $type, float $amount, array $extra = []): Model
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('المبلغ يجب أن يكون أكبر من صفر');
        }

        return DB::transaction(function () use ($type, $amount, $extra) {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $new = round((float) $locked->balance + ($type === LedgerType::Debt ? $amount : -$amount), 2);

            if ($new < 0) {
                throw new RuntimeException('المبلغ أكبر من الرصيد المستحق');
            }

            $locked->update(['balance' => $new]);

            $tx = $locked->transactions()->create($extra + [
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $new,
                'user_id' => auth()->id(),
                'shift_id' => CashierShift::openFor(auth()->id())?->id,
            ]);

            $this->refresh();

            return $tx;
        });
    }
}
