<?php

namespace App\Http\Controllers;

use App\Filament\Pages\Reports\BaseReport;
use App\Filament\Pages\Reports\CashierReport;
use App\Filament\Pages\Reports\DebtLedgerReport;
use App\Filament\Pages\Reports\InventoryMovementReport;
use App\Filament\Pages\Reports\ProfitReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Filament\Pages\Reports\TopProductsReport;
use Illuminate\Http\Request;

/** صفحة طباعة مستقلة للتقارير (محتوى التقرير وحده بلا قوائم النظام) */
class ReportPrintController extends Controller
{
    /** @var array<string, class-string<BaseReport>> */
    private const REPORTS = [
        'sales' => SalesReport::class,
        'profit' => ProfitReport::class,
        'top-products' => TopProductsReport::class,
        'inventory-movement' => InventoryMovementReport::class,
        'cashier' => CashierReport::class,
        'debt-ledger' => DebtLedgerReport::class,
    ];

    public function show(Request $request, string $report)
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        $class = self::REPORTS[$report] ?? null;

        abort_unless($class, 404);

        $filters = $request->only([
            'from', 'until', 'user_id', 'payment_method', 'customer_id', 'type',
            'warehouse_id', 'order', 'limit',
        ]);

        return view('report-print', $class::printData($filters));
    }
}
