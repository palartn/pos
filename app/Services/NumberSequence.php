<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ترقيم تسلسلي بلا فجوات: يُحجز الرقم داخل نفس عملية قاعدة البيانات الخاصة بالمستند،
 * فإن فشلت العملية (مخزون غير كافٍ مثلاً) يُلغى الحجز ويبقى الرقم متاحاً للمستند التالي.
 * ويدعم ترقيماً سنوياً يبدأ من 1 كل سنة (INV-2026-000001).
 */
class NumberSequence
{
    public const DEFAULTS = [
        'sale' => ['label' => 'فواتير البيع', 'prefix' => 'INV-'],
        'sale_return' => ['label' => 'مرتجعات البيع', 'prefix' => 'RET-'],
        'receipt_voucher' => ['label' => 'سندات القبض', 'prefix' => 'RV-'],
        'payment_voucher' => ['label' => 'سندات الصرف', 'prefix' => 'PV-'],
    ];

    /** الجدول والعمود الذي يُخزَّن فيه رقم كل مستند (لمنع أي تكرار) */
    private const TARGETS = [
        'sale' => ['sales', 'invoice_number'],
        'sale_return' => ['sale_returns', 'return_number'],
        'receipt_voucher' => ['vouchers', 'number'],
        'payment_voucher' => ['payment_vouchers', 'number'],
    ];

    private function taken(string $key, string $number): bool
    {
        [$table, $column] = self::TARGETS[$key] ?? [null, null];

        return $table !== null && DB::table($table)->where($column, $number)->exists();
    }

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

            // ترقيم سنوي: مع بداية سنة جديدة يعود العدّاد إلى 1
            if ($seq->yearly_reset) {
                $year = (int) now()->format('Y');

                if ($seq->period !== $year) {
                    $seq->period = $year;
                    $seq->next_number = 1;
                }
            }

            $number = $seq->next_number;

            // حماية: إن كان الرقم مستخدماً فعلاً (بعد تغيير الإعدادات مثلاً) يُتخطى إلى أول رقم حرّ
            while ($this->taken($key, $seq->format($number))) {
                $number++;
            }

            $formatted = $seq->format($number);
            $seq->next_number = $number + 1;
            $seq->save();

            return $formatted;
        });
    }
}
