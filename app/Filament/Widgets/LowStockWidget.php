<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'أصناف تحت حد التنبيه';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u && ($u->can('products.manage') || $u->can('inventory.manage'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->where('min_stock', '>', 0)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الصنف')->weight('bold'),
                Tables\Columns\TextColumn::make('stock')->label('المتوفر')
                    ->formatStateUsing(fn ($s, $record) => rtrim(rtrim(number_format((float) $s, 3, '.', ''), '0'), '.') . ' ' . $record->unit)
                    ->color('danger'),
                Tables\Columns\TextColumn::make('min_stock')->label('الحد الأدنى')
                    ->formatStateUsing(fn ($s) => rtrim(rtrim(number_format((float) $s, 3, '.', ''), '0'), '.')),
            ])
            ->emptyStateHeading('كل الأصناف فوق حد التنبيه')
            ->paginated([5, 10]);
    }
}
