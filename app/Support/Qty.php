<?php

namespace App\Support;

class Qty
{
    /**
     * تنسيق كمية بدون أصفار زائدة: 12.000 => 12 ، 0.750 => 0.75
     */
    public static function format($value): string
    {
        $text = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($text, '0'), '.');
    }
}
