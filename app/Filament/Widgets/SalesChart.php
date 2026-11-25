<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'مبيعات آخر 14 يوماً';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $from = today()->subDays(13);

        $rows = Sale::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, SUM(total) as t')
            ->groupBy('d')->pluck('t', 'd');

        $labels = [];
        $values = [];

        for ($i = 0; $i < 14; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $labels[] = substr($d, 5);
            $values[] = (float) ($rows[$d] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'المبيعات (₪)',
                'data' => $values,
                'backgroundColor' => '#10b981',
                'borderRadius' => 6,
            ]],
            'labels' => $labels,
        ];
    }
}
