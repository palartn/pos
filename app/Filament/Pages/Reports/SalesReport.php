<?php

namespace App\Filament\Pages\Reports;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Filament\Forms;

class SalesReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'تقرير المبيعات';
    protected static ?string $title = 'تقرير المبيعات';
    protected static ?string $slug = 'reports/sales';
    protected static ?int $navigationSort = 1;

    protected function extraFilters(): array
    {
        return [
            Forms\Components\Select::make('user_id')->label('الكاشير')
                ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                ->placeholder('كل الكاشير')->live(),
            Forms\Components\Select::make('payment_method')->label('طريقة الدفع')
                ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all())
                ->placeholder('كل الطرق')->live(),
        ];
    }

    protected function report(): array
    {
        [$from, $until] = $this->range();
        $userId = $this->filters['user_id'] ?? null;
        $method = $this->filters['payment_method'] ?? null;

        $sales = fn () => Sale::query()
            ->whereBetween('created_at', [$from, $until])
            ->when($userId, fn ($q, $v) => $q->where('user_id', $v))
            ->when($method, fn ($q, $v) => $q->where('payment_method', $v));

        $agg = $sales()->selectRaw(
            'COUNT(*) c, COALESCE(SUM(discount),0) d, COALESCE(SUM(total),0) t, COALESCE(SUM(due_amount),0) due'
        )->first();

        $returns = (float) SaleReturn::query()
            ->whereBetween('created_at', [$from, $until])
            ->whereHas('sale', fn ($q) => $q
                ->when($userId, fn ($q, $v) => $q->where('user_id', $v))
                ->when($method, fn ($q, $v) => $q->where('payment_method', $v)))
            ->sum('total');

        $count = (int) $agg->c;
        $net = (float) $agg->t - $returns;

        $byMethod = $sales()->selectRaw('payment_method, COUNT(*) c, SUM(total) t, SUM(paid) p, SUM(due_amount) d')
            ->groupBy('payment_method')->get();

        $byDay = $sales()->selectRaw('DATE(created_at) as d, COUNT(*) c, SUM(total) t')
            ->groupBy('d')->orderBy('d')->get();

        return [
            'stats' => [
                ['label' => 'عدد الفواتير', 'value' => number_format($count)],
                ['label' => 'إجمالي المبيعات', 'value' => $this->money($agg->t)],
                ['label' => 'الخصومات', 'value' => $this->money($agg->d)],
                ['label' => 'المرتجعات', 'value' => $this->money($returns), 'tone' => 'warn'],
                ['label' => 'صافي المبيعات', 'value' => $this->money($net), 'tone' => 'good'],
                ['label' => 'متوسط الفاتورة', 'value' => $this->money($count ? (float) $agg->t / $count : 0)],
                ['label' => 'ديون ناتجة عن البيع الآجل', 'value' => $this->money($agg->due), 'tone' => 'bad'],
            ],
            'chart' => [
                'title' => 'المبيعات اليومية',
                'labels' => $byDay->map(fn ($r) => substr($r->d, 5))->all(),
                'values' => $byDay->map(fn ($r) => (float) $r->t)->all(),
                'titles' => $byDay->map(fn ($r) => $r->d . ': ' . $this->money($r->t))->all(),
            ],
            'tables' => [
                [
                    'title' => 'حسب طريقة الدفع',
                    'headers' => ['الطريقة', 'عدد الفواتير', 'الإجمالي', 'المدفوع', 'الدين'],
                    'rows' => $byMethod->map(fn ($r) => [
                        PaymentMethod::tryFrom($r->payment_method)?->label() ?? $r->payment_method,
                        $r->c, $this->money($r->t), $this->money($r->p), $this->money($r->d),
                    ])->all(),
                ],
                [
                    'title' => 'حسب اليوم',
                    'headers' => ['التاريخ', 'عدد الفواتير', 'الإجمالي'],
                    'rows' => $byDay->map(fn ($r) => [$r->d, $r->c, $this->money($r->t)])->all(),
                    'footer' => ['الإجمالي', $count, $this->money($agg->t)],
                ],
            ],
        ];
    }
}
