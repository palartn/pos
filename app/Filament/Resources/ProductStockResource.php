<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ProductStockResource\Pages;
use App\Models\ProductStock;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductStockResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'inventory.manage';

    protected static ?string $model = ProductStock::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $modelLabel = 'رصيد';
    protected static ?string $pluralModelLabel = 'أرصدة المخازن';
    protected static ?string $navigationLabel = 'أرصدة المخازن';
    protected static ?int $navigationSort = 3;

    // الإدخال والتحويل والتسوية تتم من أزرار الصفحة فقط
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'warehouse']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')->label('الصنف')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('warehouse.name')->label('المخزن')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->label('الكمية')->sortable()
                    ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.') . ' ' . $record->product->unit)
                    ->color(fn ($record) => (float) $record->quantity <= (float) $record->product->min_stock ? 'danger' : null),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')->label('المخزن')->relationship('warehouse', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductStocks::route('/')];
    }
}
