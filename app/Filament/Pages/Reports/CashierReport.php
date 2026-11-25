<?php

namespace App\Filament\Pages\Reports;

use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Filament\Forms;

class CashierReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'تقرير الكاشير';
    protected static ?string $title = 'تقرير الكاشير';
    protected static ?string $slug = 'reports/cashier';
    protected static ?int $navigationSort = 5;

    protected function extraFilters(): array
    {
        return [
            Forms\Components\Select::make('user_id')->label('الكاشير')
                ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->placeholder('كل الكاشير')->live(),
        ];
    }

    protected function report(): array
    {
        [$from, $until] = $this->range();
        $userId = $this->filters['user_id'] ?? null;

        $sales = Sale::query()->whereBetween('created_at', [$from, $until])
            ->when($userId, fn ($q, $v) => $q->where('user_id', $v))
            ->selectRaw('user_id, COUNT(*) c, SUM(total) t, SUM(discount) d, SUM(due_amount) due')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $returns = SaleReturn::query()->whereBetween('created_at', [$from, $until])
            ->when($userId, fn ($q, $v) => $q->where('user_id', $v))
            ->selectRaw('user_id, SUM(total) t')->groupBy('user_id')->pluck('t', 'user_id');

        $shifts = CashierShift::query()->where('status', 'closed')->whereBetween('closed_at', [$from, $until])
            ->when($userId, fn ($q, $v) => $q->where('user_id', $v))
            ->selectRaw('user_id, COUNT(*) c, SUM(difference) diff')
            ->groupBy('user_id')->get()->keyBy('user_id');

        $ids = $sales->keys()->merge($returns->keys())->merge($shifts->keys())->unique()->values();
        $names = User::whereIn('id', $ids)->pluck('name', 'id');

        $tot = ['c' => 0, 't' => 0.0, 'd' => 0.0, 'due' => 0.0, 'ret' => 0.0, 'diff' => 0.0, 'sh' => 0];

        $rows = $ids->map(function ($id) use ($sales, $returns, $shifts, $names, &$tot) {
            $s = $sales->get($id);
            $sh = $shifts->get($id);
            $ret = (float) ($returns[$id] ?? 0);

            $tot['c'] += (int) ($s->c ?? 0);
            $tot['t'] += (float) ($s->t ?? 0);
            $tot['d'] += (float) ($s->d ?? 0);
            $tot['due'] += (float) ($s->due ?? 0);
            $tot['ret'] += $ret;
            $tot['diff'] += (float) ($sh->diff ?? 0);
            $tot['sh'] += (int) ($sh->c ?? 0);

            return [
                $names[$id] ?? ('#' . $id),
                (int) ($sh->c ?? 0),
                (int) ($s->c ?? 0),
                $this->money($s->t ?? 0),
                $this->money($s->d ?? 0),
                $this->money($s->due ?? 0),
                $this->money($ret),
                $this->money($sh->diff ?? 0),
            ];
        })->all();

        return [
            'note' => 'فرق الصندوق = مجموع (المعدود − المتوقع) للورديات المغلقة في الفترة؛ السالب يعني عجزاً.',
            'stats' => [
                ['label' => 'الورديات المغلقة', 'value' => number_format($tot['sh'])],
                ['label' => 'عدد الفواتير', 'value' => number_format($tot['c'])],
                ['label' => 'إجمالي المبيعات', 'value' => $this->money($tot['t']), 'tone' => 'good'],
                ['label' => 'مجموع فروقات الصندوق', 'value' => $this->money($tot['diff']), 'tone' => abs($tot['diff']) < 0.005 ? 'good' : 'bad'],
            ],
            'tables' => [[
                'title' => 'أداء الكاشير',
                'headers' => ['الكاشير', 'ورديات مغلقة', 'الفواتير', 'المبيعات', 'الخصومات', 'ديون آجلة', 'المرتجعات', 'فرق الصندوق'],
                'rows' => $rows,
                'footer' => ['الإجمالي', $tot['sh'], $tot['c'], $this->money($tot['t']), $this->money($tot['d']),
                    $this->money($tot['due']), $this->money($tot['ret']), $this->money($tot['diff'])],
            ]],
        ];
    }
}
