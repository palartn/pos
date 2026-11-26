<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StockTakeResource\Pages;
use App\Filament\Resources\StockTakeResource\RelationManagers;
use App\Filament\Support\Ledger;
use App\Models\Category;
use App\Models\StockTake;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockTakeResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'stocktake.manage';

    protected static ?string $model = StockTake::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $modelLabel = 'جرد';
    protected static ?string $pluralModelLabel = 'الجرد';
    protected static ?int $navigationSort = 5;

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    // يُحذف الجرد وهو مسودة فقط
    public static function canDelete(Model $record): bool
    {
        return static::allowed() && $record->isDraft();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بدء جرد جديد')->schema([
                Forms\Components\Select::make('warehouse_id')->label('المخزن')
                    ->relationship('warehouse', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->default(fn () => Warehouse::defaultOrCreate()->id)->required(),
                Forms\Components\Select::make('category_ids')->label('التصنيفات (اتركه فارغاً لجرد كل الأصناف)')
                    ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                    ->multiple()->searchable(),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
                Forms\Components\Placeholder::make('hint')->hiddenLabel()->columnSpanFull()
                    ->content('تُسجَّل أرصدة النظام لحظة البدء، ثم تُدخل الكميات الفعلية وتُعتمد التسوية.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('warehouse')->withCount('items'))
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('رقم الجرد')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('بدأ في')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('المخزن'),
                Tables\Columns\TextColumn::make('items_count')->label('عدد الأصناف'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($s) => $s === 'draft' ? 'مسودة' : 'معتمد')
                    ->color(fn ($s) => $s === 'draft' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('difference_value')->label('قيمة الفروقات')
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s))
                    ->color(fn ($s) => $s === null || (float) $s == 0 ? null : ((float) $s < 0 ? 'danger' : 'success')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['draft' => 'مسودة', 'approved' => 'معتمد']),
                Tables\Filters\SelectFilter::make('warehouse_id')->label('المخزن')->relationship('warehouse', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('فتح'),
                Tables\Actions\DeleteAction::make()->label('حذف المسودة'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('بيانات الجرد')->schema([
                Infolists\Components\TextEntry::make('number')->label('رقم الجرد')->weight('bold'),
                Infolists\Components\TextEntry::make('warehouse.name')->label('المخزن'),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($s) => $s === 'draft' ? 'مسودة' : 'معتمد')
                    ->color(fn ($s) => $s === 'draft' ? 'warning' : 'success'),
                Infolists\Components\TextEntry::make('user.name')->label('بدأه')->placeholder('—'),
                Infolists\Components\TextEntry::make('approved_at')->label('اعتُمد في')->dateTime('Y-m-d H:i')->placeholder('—'),
                Infolists\Components\TextEntry::make('approver.name')->label('اعتمده')->placeholder('—'),
                Infolists\Components\TextEntry::make('difference_value')->label('قيمة الفروقات (بسعر التكلفة)')
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s)),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpan(2),
            ])->columns(3),
        ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTakes::route('/'),
            'create' => Pages\CreateStockTake::route('/create'),
            'view' => Pages\ViewStockTake::route('/{record}'),
        ];
    }
}
