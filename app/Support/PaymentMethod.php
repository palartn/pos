<?php

namespace App\Support;

class PaymentMethod
{
    /**
     * طرق الدفع المتاحة (المفتاح يُخزَّن في قاعدة البيانات).
     * لإضافة طريقة جديدة أضفها هنا فقط.
     */
    public const LABELS = [
        'cash' => 'نقدي',
        'card' => 'بطاقة',
        'jawwal_pay' => 'جوال باي',
        'palpay' => 'بال باي',
        'bank_palestine' => 'بنك فلسطين',
        'other' => 'أخرى',
        'credit' => 'آجل',
    ];

    public static function options(): array
    {
        return self::LABELS;
    }

    public static function exists(string $method): bool
    {
        return array_key_exists($method, self::LABELS);
    }

    public static function label(?string $method): string
    {
        return self::LABELS[(string) $method] ?? (string) $method;
    }

    /**
     * هل يظهر حقل التحديد بجانب طريقة الدفع؟ (كل الطرق عدا النقدي والآجل)
     */
    public static function needsDetails(string $method): bool
    {
        return ! in_array($method, ['cash', 'credit'], true);
    }

    /**
     * حقل التحديد إلزامي فقط عند اختيار "أخرى".
     */
    public static function detailsRequired(string $method): bool
    {
        return $method === 'other';
    }

    public static function detailsLabel(string $method): string
    {
        return $method === 'other'
            ? 'حدّد طريقة الدفع'
            : 'رقم العملية / المرجع (اختياري)';
    }
}
