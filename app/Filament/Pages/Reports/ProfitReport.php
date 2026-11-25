<?php

namespace App\Filament\Pages\Reports;

use App\Models\CashMovement;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;

class ProfitReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'تقرير الأرباح';
    protected static ?string $title = 'تقرير الأرباح';
    protected static ?string $slug = 'reports/profit';
    protected static ?int $navigationSort = 2;

    protected function report(): array
    {
        [$from, $until] = $this->range();

        $revenue = (float) Sale::whereBetween('created_at', [$from, $until])->sum('total');
        $returns = (float) SaleReturn::whereBetween('created_at', [$from, $until])->sum('total');

        $cogs = (float) DB::table('sale_items as i')
            ->join('sales as s', 's.id', '=', 'i.sale_id')
            ->whereBetween('s.created_at', [$from, $until])
            ->selectRaw('COALESCE(SUM(i.quantity * i.cost_price), 0) as c')->value('c');

        $returnedCogs = (float) DB::table('sale_return_items as ri')
            ->join('sale_items as i', 'i.id', '=', 'ri.sale_item_id')
            ->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->whereBetween('r.created_at', [$from, $until])
            ->selectRaw('COALESCE(SUM(ri.quantity * i.cost_price), 0) as c')->value('c');

        $expenses = (float) CashMovement::where('type', 'expense')
            ->whereBetween('occurred_on', [$from->toDateString(), $until->toDateString()])->sum('amount');

        $otherIncome = (float) CashMovement::where('type', 'income')->where('category', 'other_income')
            ->whereBetween('occurred_on', [$from->toDateString(), $until->toDateString()])->sum('amount');

        $netRevenue = $revenue - $returns;
        $netCogs = $cogs - $returnedCogs;
        $gross = $netRevenue - $netCogs;
        $netProfit = $gross + $otherIncome - $expenses;
        $margin = $netRevenue > 0 ? round($gross / $netRevenue * 100, 1) : 0;

        $revByDay = Sale::whereBetween('created_at', [$from, $until])
            ->selectRaw('DATE(created_at) as d, SUM(total) t')->groupBy('d')->pluck('t', 'd');

        $costByDay = DB::table('sale_items as i')
            ->join('sales as s', 's.id', '=', 'i.sale_id')
            ->whereBetween('s.created_at', [$from, $until])
            ->selectRaw('DATE(s.created_at) as d, SUM(i.quantity * i.cost_price) c')
            ->groupBy('d')->pluck('c', 'd');

        $days = $revByDay->keys()->merge($costByDay->keys())->unique()->sort()->values();

        $dayRows = $days->map(function ($d) use ($revByDay, $costByDay) {
            $r = (float) ($revByDay[$d] ?? 0);
            $c = (float) ($costByDay[$d] ?? 0);

            return [$d, $this->money($r), $this->money($c), $this->money($r - $c)];
        })->all();

        return [
            'note' => 'التكلفة تُحسب بسعر التكلفة المسجَّل للصنف وقت البيع؛ الأصناف التي سعر تكلفتها صفر تُضخّم الربح. الجدول اليومي قبل خصم المرتجعات.',
            'stats' => [
                ['label' => 'صافي المبيعات (بعد المرتجعات)', 'value' => $this->money($netRevenue)],
                ['label' => 'تكلفة البضاعة المباعة', 'value' => $this->money($netCogs)],
                ['label' => 'الربح الإجمالي', 'value' => $this->money($gross), 'tone' => 'good'],
                ['label' => 'هامش الربح الإجمالي', 'value' => $margin . '%'],
                ['label' => 'المصروفات', 'value' => $this->money($expenses), 'tone' => 'bad'],
                ['label' => 'إيرادات أخرى', 'value' => $this->money($otherIncome)],
                ['label' => 'صافي الربح', 'value' => $this->money($netProfit), 'tone' => $netProfit >= 0 ? 'good' : 'bad'],
            ],
            'chart' => [
                'title' => 'الربح الإجمالي اليومي',
                'labels' => $days->map(fn ($d) => substr($d, 5))->all(),
                'values' => $days->map(fn ($d) => max(0, (float) ($revByDay[$d] ?? 0) - (float) ($costByDay[$d] ?? 0)))->all(),
                'titles' => $days->map(fn ($d) => $d . ': ' . $this->money((float) ($revByDay[$d] ?? 0) - (float) ($costByDay[$d] ?? 0)))->all(),
            ],
            'tables' => [
                [
                    'title' => 'حسب اليوم',
                    'headers' => ['التاريخ', 'المبيعات', 'التكلفة', 'الربح الإجمالي'],
                    'rows' => $dayRows,
                ],
            ],
        ];
    }
}
