<?php

namespace App\Filament\Support;

use App\Models\CashierShift;
use Filament\Forms\Components\Select;

class CashSource
{
    /** اختيار مصدر النقد: درج وردية مفتوحة أو الخزينة الرئيسية (فارغ) */
    public static function select(string $name = 'shift_id'): Select
    {
        return Select::make($name)
            ->label('مصدر النقد')
            ->options(fn () => CashierShift::where('status', 'open')->with('user')->get()
                ->mapWithKeys(fn ($s) => [$s->id => 'درج ' . $s->user->name . ' — ' . $s->opened_at->format('Y-m-d H:i')]))
            ->placeholder('الخزينة الرئيسية (خارج درج الكاشير)')
            ->default(fn () => CashierShift::openFor(auth()->id())?->id);
    }
}
