<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ترقيم تسلسلي بلا فجوات: يُحجز الرقم داخل نفس عملية قاعدة البيانات الخاصة بالمستند،
 * فإن فشلت العملية (مخزون غير كافٍ مثلاً) يُلغى الحجز ويبقى الرقم متاحاً للمستند التالي.
 */
class NumberSequence
{
    public const DEFAULTS = [
        'sale' => ['label' => 'فواتير البيع', 'prefix' => 'INV-'],
        'sale_return' => ['label' => 'مرتجعات البيع', 'prefix' => 'RET-'],
        'receipt_voucher' => ['label' => 'سندات القبض', 'prefix' => 'RV-'],
        'payment_voucher' => ['label' => 'سندات الصرف', 'prefix' => 'PV-'],
    ];

    public function next(string $key): string
    {
        return DB::transaction(function () use ($key) {
            $seq = DocumentSequence::where('key', $key)->lockForUpdate()->first();

            if (! $seq) {
                $d = self::DEFAULTS[$key] ?? throw new InvalidArgumentException("نوع ترقيم غير معروف: {$key}");

                $seq = DocumentSequence::create([
                    'key' => $key, 'label' => $d['label'], 'prefix' => $d['prefix'], 'padding' => 6, 'next_number' => 1,
                ]);
            }

            $number = $seq->next_number;
            $seq->update(['next_number' => $number + 1]);

            return $seq->format($number);
        });
    }
}
