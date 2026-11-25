<?php

namespace App\Filament\Pages\Reports;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Forms;
use Illuminate\Support\Facades\DB;

class InventoryMovementReport extends BaseReport
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';
    protected static ?string $navigationLabel = 'حركة المخزون';
    protected static ?string $title = 'تقرير حركة المخزون';
    protected static ?string $slug = 'reports/inventory-movement';
    protected static ?int $navigationSort = 4;

    protected function extraFilters(): array
    {
        return [
            Forms\Components\Select::make('warehouse_id')->label('المخزن')
                ->options(fn () => Warehouse::orderBy('name')->pluck('name', 'id'))
                ->placeholder('كل المخازن')->live(),
        ];
    }

    protected function report(): array
    {
        [$from, $until] = $this->range();
        $wh = $this->filters['warehouse_id'] ?? null;
        $f = $from->toDateTimeString();

        $rows = DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->when($wh, fn ($q, $v) => $q->where('m.warehouse_id', $v))
            ->where('m.created_at', '<=', $until)
            ->selectRaw(
                'p.id, p.name, p.unit,
                 SUM(CASE WHEN m.created_at < ? THEN m.quantity ELSE 0 END) as opening,
                 SUM(CASE WHEN m.created_at >= ? AND m.quantity > 0 THEN m.quantity ELSE 0 END) as qty_in,
                 SUM(CASE WHEN m.created_at >= ? AND m.quantity < 0 THEN m.quantity ELSE 0 END) as qty_out,
                 SUM(m.quantity) as closing',
                [$f, $f, $f]
            )
            ->groupBy('p.id', 'p.name', 'p.unit')
            ->orderBy('p.name')
            ->limit(500)
            ->get()
            ->filter(fn ($r) => (float) $r->opening != 0 || (float) $r->qty_in != 0 || (float) $r->qty_out != 0);

        $byType = StockMovement::query()
            ->whereBetween('created_at', [$from, $until])
            ->when($wh, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->selectRaw('type, COUNT(*) c,
                SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as i,
                SUM(CASE WHEN quantity < 0 THEN quantity ELSE 0 END) as o')
            ->groupBy('type')->get();

        return [
            'note' => 'يعرض أول 500 صنف لها حركة في الفترة أو رصيد افتتاحي. الرصيد الافتتاحي = مجموع الحركات قبل تاريخ البداية.',
            'tables' => [
                [
                    'title' => 'ملخص حسب نوع الحركة',
                    'headers' => ['النوع', 'عدد الحركات', 'الوارد', 'الصادر'],
                    'rows' => $byType->map(fn ($r) => [
                        StockService::TYPES[$r->type] ?? $r->type, $r->c, $this->qty($r->i), $this->qty($r->o),
                    ])->all(),
                ],
                [
                    'title' => 'حركة الأصناف',
                    'headers' => ['الصنف', 'الوحدة', 'رصيد أول المدة', 'الوارد', 'الصادر', 'رصيد آخر المدة'],
                    'rows' => $rows->values()->map(fn ($r) => [
                        $r->name, $r->unit, $this->qty($r->opening), $this->qty($r->qty_in),
                        $this->qty($r->qty_out), $this->qty($r->closing),
                    ])->all(),
                ],
            ],
        ];
    }
}
