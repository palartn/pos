<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmployeeTransactionType: string implements HasColor, HasLabel
{
    case Advance = 'advance';     // سلفة (تُخصم من الراتب)
    case Bonus = 'bonus';         // مكافأة / إضافي (تُضاف للراتب)
    case Deduction = 'deduction'; // خصم (غياب / جزاء)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Advance => 'سلفة',
            self::Bonus => 'مكافأة / إضافي',
            self::Deduction => 'خصم',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Advance => 'warning',
            self::Bonus => 'success',
            self::Deduction => 'danger',
        };
    }
}
