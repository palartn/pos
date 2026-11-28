<?php

namespace App\Filament\Pages\Reports;

use App\Enums\LedgerType;
use App\Models\Customer;
use App\Services\DebtLedgerService;
use Filament\Forms;

class DebtLedgerReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'دفتر الديون';
    protected static ?string $title = 'دفتر الديون اليومي';
    protected static ?string $slug = 'reports/debt-ledger';
    protected static ?int $navigationSort = 6;

    protected function extraFilters(): array
    {
        return [
            Forms\Components\Select::make('customer_id')->label('العميل')
                ->options(fn () => Customer::orderBy('name')->pluck('name', 'id'))
                ->searchable()->placeholder('كل العملاء')->live(),
            Forms\Components\Select::make('type')->label('النوع')
                ->options([
                    LedgerType::Debt->value => 'ديون فقط',
                    LedgerType::Payment->value => 'دفعات فقط',
                ])
                ->placeholder('ديون ودفعات')->live(),
        ];
    }

    protected function report(): array
    {
        [$from, $until] = $this->range();
        $customerId = $this->filters['customer_id'] ?? null;
        $type = $this->filters['type'] ?? null;
        $svc = app(DebtLedgerService::class);

        $summary = $svc->summary($from, $until, $customerId);

        $rows = $svc->entries($from, $until, $customerId, $type)->map(function ($e) {
            $detail = trim($e['description'] . ($e['items'] ? ' — ' . $e['items'] : ''));

            return [
                $e['date']->format('Y-m-d H:i'),
                $e['customer'],
                $e['type']->getLabel(),
                $detail !== '' ? $detail : '—',
                ($e['type'] === LedgerType::Debt ? '+' : '−') . $this->money($e['amount']),
                $this->money($e['balance_after']),
            ];
        })->all();

        $perCustomer = $svc->perCustomer($from, $until, $customerId)->map(fn ($c) => [
            $c['customer'], $this->money($c['debts']), $this->money($c['payments']), $this->money($c['balance']),
        ])->all();

        return [
            'note' => 'الأرقام تخص حركات الفترة المحددة. «الدين الحالي» هو الرصيد الفعلي اليوم لكل عميل. يعرض الدفتر أول 1000 حركة.',
            'stats' => [
                ['label' => 'ديون الفترة', 'value' => $this->money($summary['debts']), 'tone' => 'bad'],
                ['label' => 'دفعات الفترة', 'value' => $this->money($summary['payments']), 'tone' => 'good'],
                ['label' => 'صافي التغيّر', 'value' => $this->money($summary['net']), 'tone' => $summary['net'] > 0 ? 'warn' : 'good'],
                ['label' => 'إجمالي الديون القائمة اليوم', 'value' => $this->money($summary['outstanding']), 'tone' => 'bad'],
                ['label' => 'عدد المدينين', 'value' => number_format($summary['debtors'])],
            ],
            'tables' => [
                [
                    'title' => 'ملخص لكل عميل',
                    'headers' => ['العميل', 'ديون الفترة', 'دفعات الفترة', 'الدين الحالي'],
                    'rows' => $perCustomer,
                ],
                [
                    'title' => 'حركات الديون والدفعات',
                    'headers' => ['التاريخ', 'العميل', 'النوع', 'البيان / الأصناف', 'المبلغ', 'الرصيد بعد الحركة'],
                    'rows' => $rows,
                ],
            ],
        ];
    }
}
