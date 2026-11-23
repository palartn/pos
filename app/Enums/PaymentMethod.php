<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case JawwalPay = 'jawwal_pay';
    case PalPay = 'palpay';
    case BankOfPalestine = 'bank_of_palestine';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقداً',
            self::Credit => 'آجل (دين)',
            self::JawwalPay => 'جوال باي',
            self::PalPay => 'بال باي',
            self::BankOfPalestine => 'بنك فلسطين',
            self::Other => 'أخرى',
        };
    }

    public function needsDetails(): bool
    {
        return in_array($this, [self::JawwalPay, self::PalPay, self::BankOfPalestine, self::Other]);
    }
}
