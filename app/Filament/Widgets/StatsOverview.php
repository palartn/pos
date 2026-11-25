<?php

namespace App\Filament\Widgets;

use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Services\BackupService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'نظرة سريعة';

    public static function canView(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    protected function getStats(): array
    {
        $money = fn ($v) => '₪' . number_format((float) $v, 2);

        $today = Sale::whereDate('created_at', today())
            ->selectRaw('COUNT(*) c, COALESCE(SUM(total), 0) t')->first();
        $returns = (float) SaleReturn::whereDate('created_at', today())->sum('total');

        $debts = Customer::where('balance', '>', 0)->selectRaw('COUNT(*) c, COALESCE(SUM(balance), 0) t')->first();
        $dues = Supplier::where('balance', '>', 0)->selectRaw('COUNT(*) c, COALESCE(SUM(balance), 0) t')->first();

        $low = Product::where('is_active', true)->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')->count();

        $openShifts = CashierShift::where('status', 'open')->count();

        try {
            $backup = app(BackupService::class)->latest();
        } catch (\Throwable) {
            $backup = null;
        }

        return [
            Stat::make('مبيعات اليوم', $money((float) $today->t - $returns))
                ->description($today->c . ' فاتورة' . ($returns > 0 ? ' — مرتجعات ' . $money($returns) : ''))
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),
            Stat::make('ديون العملاء', $money($debts->t))
                ->description($debts->c . ' عميل عليهم دين')
                ->descriptionIcon('heroicon-m-users')
                ->color($debts->t > 0 ? 'danger' : 'gray'),
            Stat::make('مستحقات الموردين', $money($dues->t))
                ->description($dues->c . ' مورد')
                ->descriptionIcon('heroicon-m-truck')
                ->color($dues->t > 0 ? 'warning' : 'gray'),
            Stat::make('أصناف تحت حد التنبيه', $low)
                ->description('تحتاج إعادة تزويد')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($low > 0 ? 'danger' : 'success'),
            Stat::make('ورديات مفتوحة', $openShifts)
                ->descriptionIcon('heroicon-m-clock')
                ->color($openShifts > 0 ? 'info' : 'gray'),
            Stat::make('آخر نسخة احتياطية', $backup ? $backup['created_at']->diffForHumans() : 'لا توجد')
                ->description($backup ? $backup['created_at']->format('Y-m-d H:i') : 'أنشئ نسخة من «الإدارة ← النسخ الاحتياطي»')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color(! $backup || $backup['created_at']->lt(now()->subHours(48)) ? 'danger' : 'success'),
        ];
    }
}
