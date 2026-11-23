<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LedgerType: string implements HasColor, HasLabel
{
    case Debt = 'debt';       // يزيد الرصيد (دين على العميل / مستحق للمورد)
    case Payment = 'payment'; // ينقص الرصيد (دفعة)

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Debt => 'دين / مستحق',
            self::Payment => 'دفعة',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Debt => 'danger',
            self::Payment => 'success',
        };
    }
}
