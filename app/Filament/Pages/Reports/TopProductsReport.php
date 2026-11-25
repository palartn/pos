<?php

namespace App\Filament\Pages\Reports;

use Filament\Forms;
use Illuminate\Support\Facades\DB;

class TopProductsReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'الأكثر مبيعاً';
    protected static ?string $title = 'الأصناف الأكثر مبيعاً';
    protected static ?string $slug = 'reports/top-products';
    protected static ?int $navigationSort = 3;

    protected function defaultFilters(): array
    {
        return ['order' => 'qty', 'limit' => 20];
    }

    protected function extraFilters(): array
    {
        return [
            Forms\Components\Select::make('order')->label('الترتيب حسب')
                ->options(['qty' => 'الكمية', 'revenue' => 'قيمة المبيعات', 'profit' => 'الربح'])
                ->selectablePlaceholder(false)->live(),
            Forms\Components\Select::make('limit')->label('عدد الأصناف')
                ->options([10 => 10, 20 => 20, 50 => 50, 100 => 100])
                ->selectablePlaceholder(false)->live(),
        ];
    }

    protected function report(): array
    {
        [$from, $until] = $this->range();
        $order = $this->filters['order'] ?? 'qty';
        $limit = (int) ($this->filters['limit'] ?? 20);

        $orderSql = match ($order) {
            'revenue' => 'rev DESC',
            'profit' => '(SUM(i.total) - SUM(i.quantity * i.cost_price)) DESC',
            default => 'q DESC',
        };

        $rows = DB::table('sale_items as i')
            ->join('sales as s', 's.id', '=', 'i.sale_id')
            ->whereBetween('s.created_at', [$from, $until])
            ->selectRaw('i.product_id, i.product_name, SUM(i.quantity) as q, SUM(i.total) as rev, SUM(i.quantity * i.cost_price) as cost')
            ->groupBy('i.product_id', 'i.product_name')
            ->orderByRaw($orderSql)
            ->limit($limit)
            ->get();

        return [
            'note' => 'القيم قبل خصم الفاتورة والمرتجعات، وتُحسب من أسطر الفواتير.',
            'chart' => [
                'title' => 'الكمية المباعة',
                'labels' => $rows->take(15)->map(fn ($r) => mb_substr($r->product_name, 0, 8))->all(),
                'values' => $rows->take(15)->map(fn ($r) => (float) $r->q)->all(),
                'titles' => $rows->take(15)->map(fn ($r) => $r->product_name . ': ' . $this->qty($r->q))->all(),
            ],
            'tables' => [[
                'title' => 'الأصناف',
                'headers' => ['#', 'الصنف', 'الكمية المباعة', 'قيمة المبيعات', 'التكلفة', 'الربح'],
                'rows' => $rows->values()->map(fn ($r, $i) => [
                    $i + 1, $r->product_name, $this->qty($r->q),
                    $this->money($r->rev), $this->money($r->cost), $this->money((float) $r->rev - (float) $r->cost),
                ])->all(),
            ]],
        ];
    }
}
