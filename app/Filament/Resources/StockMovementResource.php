<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Services\StockService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StockMovementResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'inventory.manage';

    protected static ?string $model = StockMovement::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $modelLabel = 'حركة';
    protected static ?string $pluralModelLabel = 'حركة المخزون';
    protected static ?string $navigationLabel = 'حركة المخزون';
    protected static ?int $navigationSort = 4;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'warehouse', 'user']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('الصنف')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('warehouse.name')->label('المخزن'),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn ($state) => StockService::TYPES[$state] ?? $state),
                Tables\Columns\TextColumn::make('quantity')->label('الكمية')
                    ->formatStateUsing(fn ($state) => ((float) $state > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.'))
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : 'success')->weight('bold'),
                Tables\Columns\TextColumn::make('balance_after')->label('الرصيد بعد الحركة')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.')),
                Tables\Columns\TextColumn::make('reference')->label('المرجع')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('notes')->label('ملاحظات')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')->label('المخزن')->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('type')->label('النوع')->options(StockService::TYPES),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStockMovements::route('/')];
    }
}
