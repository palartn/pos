<?php

namespace App\Support;

class Money
{
    /**
     * تنسيق مبلغ مالي بالعملة المحددة في config/store.php
     * مثال: Money::format(12.5) => "12.50 ₪"
     */
    public static function format($amount): string
    {
        $decimals = (int) config('store.currency_decimals', 2);
        $symbol = config('store.currency_symbol', '₪');

        return number_format((float) $amount, $decimals) . ' ' . $symbol;
    }
}
