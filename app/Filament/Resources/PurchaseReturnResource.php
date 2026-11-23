<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Filament\Support\Ledger;
use App\Filament\Support\ProductSelect;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'purchases.manage';

    protected static ?string $model = PurchaseReturn::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'المشتريات';
    protected static ?string $modelLabel = 'مرتجع شراء';
    protected static ?string $pluralModelLabel = 'مرتجعات الشراء';
    protected static ?int $navigationSort = 2;

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات المرتجع')->schema([
                Forms\Components\Select::make('supplier_id')->label('المورد')
                    ->relationship('supplier', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->searchable()->preload()->required(),
                Forms\Components\Select::make('warehouse_id')->label('المخزن المُخرِج منه')
                    ->relationship('warehouse', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->default(fn () => Warehouse::defaultOrCreate()->id)->required(),
                Forms\Components\DatePicker::make('return_date')->label('التاريخ')->default(now())->required(),
            ])->columns(3),

            Forms\Components\Section::make('الأصناف المرتجعة')->schema([
                Forms\Components\Repeater::make('items')
                    ->hiddenLabel()
                    ->schema([
                        ProductSelect::make()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('unit_cost', (float) Product::whereKey($state)->value('cost_price'));
                                }
                            })
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('quantity')->label('الكمية')
                            ->numeric()->step(0.001)->minValue(0.001)->default(1)->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('unit_cost')->label('سعر الشراء (₪)')
                            ->numeric()->step(0.01)->minValue(0)->required()->live(onBlur: true),
                    ])
                    ->columns(4)
                    ->defaultItems(1)->minItems(1)
                    ->addActionLabel('إضافة صنف')
                    ->live(),
            ]),

            Forms\Components\Section::make('الحساب')->schema([
                Forms\Components\Placeholder::make('total_view')->label('إجمالي المرتجع')
                    ->content(fn (Get $get) => Ledger::money(ProductSelect::linesTotal($get('items')))),
                Forms\Components\Placeholder::make('credited_view')->label('خُصم من مستحقات المورد')
                    ->content(fn ($record) => Ledger::money($record?->credited))
                    ->visible(fn ($record) => $record !== null),
                Forms\Components\Placeholder::make('refunded_view')->label('أُعيد نقداً من المورد')
                    ->content(fn ($record) => Ledger::money($record?->refunded))
                    ->visible(fn ($record) => $record !== null),
                Forms\Components\Textarea::make('notes')->label('سبب الإرجاع / ملاحظات')->columnSpanFull(),
                Forms\Components\Placeholder::make('hint')->hiddenLabel()
                    ->content('يُخصم المرتجع من المستحق للمورد أولاً، وما زاد عن المستحق يُسجَّل كمبلغ مُسترَد نقداً.')
                    ->visible(fn ($record) => $record === null)->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('return_number')->label('رقم المرتجع')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('return_date')->label('التاريخ')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('credited')->label('خُصم من المستحق')->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('refunded')->label('مُسترَد نقداً')->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('warehouse.name')->label('المخزن')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')->label('المورد')->relationship('supplier', 'name'),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('عرض')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'view' => Pages\ViewPurchaseReturn::route('/{record}'),
        ];
    }
}
