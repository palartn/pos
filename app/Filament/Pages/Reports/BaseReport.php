<?php

namespace App\Filament\Pages\Reports;

use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * أساس مشترك للتقارير: فلتر تاريخ + حقول إضافية، والناتج مصفوفة:
 * ['note' => '', 'stats' => [[label, value, tone]], 'chart' => [...], 'tables' => [[title, headers, rows, footer]]]
 */
abstract class BaseReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.report';
    protected static ?string $navigationGroup = 'التقارير';

    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'until' => now()->toDateString(),
        ] + $this->defaultFilters());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Forms\Components\Grid::make(['default' => 1, 'md' => 4])->schema(array_merge([
                    Forms\Components\DatePicker::make('from')->label('من تاريخ')->live(),
                    Forms\Components\DatePicker::make('until')->label('إلى تاريخ')->live(),
                ], $this->extraFilters())),
            ]);
    }

    protected function extraFilters(): array
    {
        return [];
    }

    protected function defaultFilters(): array
    {
        return [];
    }

    abstract protected function report(): array;

    /** معرّف التقرير في رابط الطباعة (sales, profit, ...) */
    public static function printKey(): string
    {
        return Str::after(static::getSlug(), 'reports/');
    }

    /** رابط صفحة طباعة مستقلة بالفلاتر الحالية */
    public function printUrl(): string
    {
        $filters = array_filter($this->filters ?? [], fn ($v) => $v !== null && $v !== '');

        return route('reports.print', array_merge(['report' => static::printKey()], $filters));
    }

    /** بيانات التقرير للطباعة (تُستدعى من ReportPrintController) */
    public static function printData(array $filters): array
    {
        $page = app(static::class);
        $page->filters = $filters + [
            'from' => now()->startOfMonth()->toDateString(),
            'until' => now()->toDateString(),
        ];

        return [
            'title' => (string) $page->getTitle(),
            'filters' => $page->filters,
            'report' => $page->report(),
        ];
    }

    #[Computed]
    public function reportData(): array
    {
        return $this->report();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(): array
    {
        $from = Carbon::parse($this->filters['from'] ?? now()->startOfMonth())->startOfDay();
        $until = Carbon::parse($this->filters['until'] ?? now())->endOfDay();

        if ($until->lt($from)) {
            [$from, $until] = [$until->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $until];
    }

    protected function money(mixed $v): string
    {
        return '₪' . number_format((float) $v, 2);
    }

    protected function qty(mixed $v): string
    {
        $s = rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
