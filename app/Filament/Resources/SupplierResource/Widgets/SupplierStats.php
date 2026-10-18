<?php

namespace App\Filament\Resources\SupplierResource\Widgets;

use App\Models\Supplier;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $owed = (float) Supplier::where('balance', '>', 0)->sum('balance');
        $owedCount = Supplier::where('balance', '>', 0)->count();
        $credit = abs((float) Supplier::where('balance', '<', 0)->sum('balance'));

        return [
            Stat::make('إجمالي المستحق للموردين (علينا)', Money::format($owed))
                ->description($owedCount . ' مورد')
                ->color('danger'),

            Stat::make('إجمالي رصيدنا عند الموردين (لنا)', Money::format($credit))
                ->color('success'),

            Stat::make('عدد الموردين', Supplier::count()),
        ];
    }
}
