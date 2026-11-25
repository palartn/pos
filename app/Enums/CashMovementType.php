<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CashMovementType: string implements HasColor, HasLabel
{
    case Income = 'income';
    case Expense = 'expense';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Income => 'إيراد',
            self::Expense => 'مصروف',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }

    public function categories(): array
    {
        return match ($this) {
            self::Income => [
                'deposit' => 'إيداع / رأس مال',
                'other_income' => 'إيراد آخر',
            ],
            self::Expense => [
                'rent' => 'إيجار',
                'salaries' => 'رواتب',
                'employee_advance' => 'سلف موظفين',
                'utilities' => 'كهرباء وماء واتصالات',
                'maintenance' => 'صيانة',
                'supplies' => 'نثريات ومستلزمات',
                'other_expense' => 'مصروفات أخرى',
            ],
        };
    }

    public static function categoryLabel(?string $key): string
    {
        foreach (self::cases() as $case) {
            if (isset($case->categories()[$key])) {
                return $case->categories()[$key];
            }
        }

        return (string) $key;
    }
}
