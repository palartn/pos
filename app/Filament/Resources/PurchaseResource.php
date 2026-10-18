<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\PaymentMethod;
use App\Support\Qty;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'المشتريات';
    protected static ?string $navigationLabel = 'فواتير الشراء';
    protected static ?string $modelLabel = 'فاتورة شراء';
    protected static ?string $pluralModelLabel = 'فواتير الشراء';
    protected static ?int $navigationSort = 2;

    // فواتير الشراء لا تُعدَّل ولا تُحذف لأنها تؤثر على المخزون وحساب المورد
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['supplier', 'user']);
    }

    /**
     * مجموع أصناف الفاتورة من حالة النموذج.
     */
    public static function linesTotal($lines): float
    {
        return round(
            collect($lines ?? [])->sum(
                fn ($line) => round((float) ($line['quantity'] ?? 0) * (float) ($line['unit_cost'] ?? 0), 2)
            ),
            2
        );
    }

    protected static function netTotal(Forms\Get $get): float
    {
        return max(0, round(static::linesTotal($get('lines')) - (float) $get('discount'), 2));
    }

    public static function form(Form $form): Form
    {
        $currency = config('store.currency_symbol', '₪');

        return $form->schema([
            Forms\Components\Section::make('بيانات الفاتورة')
                ->schema([
                    Forms\Components\Select::make('supplier_id')
                        ->label('المورد')
                        ->relationship('supplier', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->default(fn () => request()->query('supplier'))
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('اسم المورد')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('phone')
                                ->label('رقم الهاتف')
                                ->tel()
                                ->maxLength(30),
                        ]),

                    Forms\Components\Placeholder::make('supplier_balance')
                        ->label('رصيد المورد الحالي')
                        ->visible(fn (Forms\Get $get) => filled($get('supplier_id')))
                        ->content(fn (Forms\Get $get) => SupplierResource::balanceLabel(
                            Supplier::find($get('supplier_id'))?->balance ?? 0
                        )),

                    Forms\Components\DatePicker::make('purchase_date')
                        ->label('تاريخ الفاتورة')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),

                    Forms\Components\TextInput::make('supplier_invoice_number')
                        ->label('رقم فاتورة المورد (اختياري)')
                        ->maxLength(100),
                ])
                ->columns(2),

            Forms\Components\Section::make('الأصناف')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->label('')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('إضافة صنف')
                        ->reorderable(false)
                        ->cloneable(false)
                        ->columns(5)
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('المنتج (اسم أو باركود)')
                                ->searchable()
                                ->required()
                                ->live()
                                ->distinct()
                                ->columnSpan(2)
                                ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                    ->where(fn ($q) => $q
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('barcode', 'like', "%{$search}%"))
                                    ->orderBy('name')
                                    ->limit(20)
                                    ->pluck('name', 'id')
                                    ->all())
                                ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->name)
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    $product = $state ? Product::find($state) : null;

                                    if ($product) {
                                        $set('unit_cost', (float) $product->purchase_price);
                                    }
                                }),

                            Forms\Components\TextInput::make('quantity')
                                ->label('الكمية')
                                ->numeric()
                                ->step(0.001)
                                ->minValue(0.001)
                                ->default(1)
                                ->required()
                                ->live(onBlur: true)
                                ->rules([
                                    fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                        $product = $get('product_id')
                                            ? Product::with('unit')->find($get('product_id'))
                                            : null;

                                        if ($product && $product->unit && ! $product->unit->allow_decimal
                                            && floor((float) $value) != (float) $value) {
                                            $fail('وحدة "' . $product->unit->name . '" لا تقبل كميات عشرية.');
                                        }
                                    },
                                ]),

                            Forms\Components\TextInput::make('unit_cost')
                                ->label('تكلفة الوحدة')
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0)
                                ->required()
                                ->live(onBlur: true)
                                ->suffix($currency),

                            Forms\Components\Placeholder::make('line_total')
                                ->label('الإجمالي')
                                ->content(fn (Forms\Get $get) => Money::format(
                                    round((float) $get('quantity') * (float) $get('unit_cost'), 2)
                                )),
                        ]),
                ]),

            Forms\Components\Section::make('الحساب والدفع')
                ->schema([
                    Forms\Components\Placeholder::make('subtotal_view')
                        ->label('مجموع الأصناف')
                        ->content(fn (Forms\Get $get) => Money::format(static::linesTotal($get('lines')))),

                    Forms\Components\TextInput::make('discount')
                        ->label('الخصم')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->suffix($currency)
                        ->rules([
                            fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                if ((float) $value > static::linesTotal($get('lines'))) {
                                    $fail('الخصم أكبر من مجموع الأصناف.');
                                }
                            },
                        ]),

                    Forms\Components\Placeholder::make('total_view')
                        ->label('إجمالي الفاتورة')
                        ->content(fn (Forms\Get $get) => Money::format(static::netTotal($get))),

                    Forms\Components\TextInput::make('paid')
                        ->label('المدفوع للمورد الآن')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->live(onBlur: true)
                        ->suffix($currency)
                        ->helperText('اتركه صفراً إذا كانت الفاتورة آجلة بالكامل'),

                    Forms\Components\Select::make('payment_method')
                        ->label('طريقة الدفع')
                        ->options(collect(PaymentMethod::options())->except('credit')->all())
                        ->default('cash')
                        ->live()
                        ->visible(fn (Forms\Get $get) => (float) $get('paid') > 0)
                        ->required(fn (Forms\Get $get) => (float) $get('paid') > 0),

                    Forms\Components\TextInput::make('payment_reference')
                        ->label(fn (Forms\Get $get) => PaymentMethod::detailsLabel((string) $get('payment_method')))
                        ->visible(fn (Forms\Get $get) => (float) $get('paid') > 0
                            && PaymentMethod::needsDetails((string) $get('payment_method')))
                        ->required(fn (Forms\Get $get) => PaymentMethod::detailsRequired((string) $get('payment_method')))
                        ->maxLength(255),

                    Forms\Components\Toggle::make('from_drawer')
                        ->label('يُخصم من درج الكاشير')
                        ->helperText('يتطلب جلسة كاشير مفتوحة. أوقفه إذا دُفع المبلغ من خارج الدرج')
                        ->default(true)
                        ->visible(fn (Forms\Get $get) => (float) $get('paid') > 0 && $get('payment_method') === 'cash'),

                    Forms\Components\Placeholder::make('remaining_view')
                        ->label('المتبقي (يُضاف لحساب المورد)')
                        ->content(function (Forms\Get $get) {
                            $remaining = round(static::netTotal($get) - (float) $get('paid'), 2);

                            return $remaining >= 0
                                ? Money::format($remaining)
                                : 'دفعة زائدة ' . Money::format(abs($remaining)) . ' (تصبح رصيداً لنا عند المورد)';
                        }),

                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('بيانات الفاتورة')
                ->schema([
                    Infolists\Components\TextEntry::make('invoice_number')
                        ->label('رقم الفاتورة')
                        ->copyable(),

                    Infolists\Components\TextEntry::make('supplier.name')
                        ->label('المورد'),

                    Infolists\Components\TextEntry::make('supplier_invoice_number')
                        ->label('فاتورة المورد')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('purchase_date')
                        ->label('التاريخ')
                        ->date('Y-m-d'),

                    Infolists\Components\TextEntry::make('user.name')
                        ->label('سجّلها'),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('ملاحظات')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Infolists\Components\Section::make('الأصناف')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')
                                ->label('المنتج')
                                ->placeholder('منتج محذوف'),

                            Infolists\Components\TextEntry::make('quantity')
                                ->label('الكمية')
                                ->formatStateUsing(fn ($state) => Qty::format($state)),

                            Infolists\Components\TextEntry::make('unit_cost')
                                ->label('تكلفة الوحدة')
                                ->formatStateUsing(fn ($state) => Money::format($state)),

                            Infolists\Components\TextEntry::make('total')
                                ->label('الإجمالي')
                                ->formatStateUsing(fn ($state) => Money::format($state)),
                        ])
                        ->columns(4),
                ]),

            Infolists\Components\Section::make('المبالغ')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')
                        ->label('المجموع')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('discount')
                        ->label('الخصم')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('total')
                        ->label('الإجمالي')
                        ->weight('bold')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('paid')
                        ->label('المدفوع عند الشراء')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('remaining')
                        ->label('المتبقي وقتها')
                        ->state(fn (Purchase $record) => Money::format(max(0, round((float) $record->total - (float) $record->paid, 2)))),
                ])
                ->columns(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('supplier_invoice_number')
                    ->label('فاتورة المورد')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid')
                    ->label('المدفوع')
                    ->formatStateUsing(fn ($state) => Money::format($state)),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('المتبقي')
                    ->state(fn (Purchase $record) => max(0, round((float) $record->total - (float) $record->paid, 2)))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('سجّلها')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('المورد')
                    ->relationship('supplier', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('purchase_date', '<=', $date));
                    }),

                Tables\Filters\Filter::make('unpaid')
                    ->label('فيها مبلغ متبقٍ')
                    ->query(fn (Builder $query) => $query->whereColumn('paid', '<', 'total')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'view' => Pages\ViewPurchase::route('/{record}'),
        ];
    }
}
