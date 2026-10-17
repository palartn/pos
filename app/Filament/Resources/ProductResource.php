<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Unit;
use App\Support\Money;
use App\Support\Qty;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'المنتجات';
    protected static ?string $modelLabel = 'منتج';
    protected static ?string $pluralModelLabel = 'المنتجات';
    protected static ?int $navigationSort = 2;

    /**
     * عدّاد المنتجات التي وصلت لحدّ التنبيه بجانب اسم القائمة.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Product::where('is_active', true)
            ->whereColumn('quantity', '<=', 'min_quantity')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * يولّد باركود داخلي بصيغة EAN-13 غير مكرر.
     */
    public static function generateBarcode(): string
    {
        do {
            $base = '200' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

            $sum = 0;
            foreach (str_split($base) as $i => $digit) {
                $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
            }

            $code = $base . ((10 - $sum % 10) % 10);
        } while (Product::where('barcode', $code)->exists());

        return $code;
    }

    /**
     * قاعدة تحقق: الوحدات غير الوزنية لا تقبل كميات عشرية.
     */
    public static function decimalRules(): array
    {
        return [
            fn (Forms\Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                $unit = $get('unit_id') ? Unit::find($get('unit_id')) : null;

                if ($unit && ! $unit->allow_decimal && floor((float) $value) != (float) $value) {
                    $fail('وحدة "' . $unit->name . '" لا تقبل كميات عشرية.');
                }
            },
        ];
    }

    public static function form(Form $form): Form
    {
        $currency = config('store.currency_symbol', '₪');

        return $form->schema([
            Forms\Components\Section::make('بيانات المنتج')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم المنتج')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('barcode')
                        ->label('الباركود')
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('generateBarcode')
                                ->label('توليد باركود')
                                ->icon('heroicon-m-sparkles')
                                ->action(fn (Forms\Set $set) => $set('barcode', static::generateBarcode()))
                        ),

                    Forms\Components\Select::make('category_id')
                        ->label('القسم')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('اسم القسم')
                                ->required()
                                ->maxLength(255),
                        ]),

                    Forms\Components\Select::make('unit_id')
                        ->label('وحدة القياس')
                        ->relationship('unit', 'name')
                        ->live()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->label('اسم الوحدة')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('symbol')
                                ->label('الرمز (اختياري)')
                                ->maxLength(20),
                            Forms\Components\Toggle::make('allow_decimal')
                                ->label('يقبل كميات عشرية (للوزن)'),
                        ]),
                ])
                ->columns(2),

            Forms\Components\Section::make('الأسعار والمخزون')
                ->schema([
                    Forms\Components\TextInput::make('purchase_price')
                        ->label('سعر الشراء')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->suffix($currency),

                    Forms\Components\TextInput::make('sale_price')
                        ->label('سعر البيع')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->suffix($currency),

                    Forms\Components\TextInput::make('quantity')
                        ->label('الكمية الحالية')
                        ->numeric()
                        ->step(0.001)
                        ->rules(static::decimalRules())
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('تنخفض تلقائياً مع كل عملية بيع'),

                    Forms\Components\TextInput::make('min_quantity')
                        ->label('حدّ التنبيه')
                        ->numeric()
                        ->step(0.001)
                        ->rules(static::decimalRules())
                        ->minValue(0)
                        ->default(5)
                        ->required()
                        ->helperText('يتحول لون الكمية للأحمر عند الوصول لهذا الحد'),
                ])
                ->columns(2),

            Forms\Components\Section::make('إضافات')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->label('صورة المنتج')
                        ->image()
                        ->directory('products')
                        ->maxSize(2048),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعّال (يظهر في نقطة البيع)')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('القسم')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit.name')
                    ->label('الوحدة')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('purchase_price')
                    ->label('سعر الشراء')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('سعر البيع')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Qty::format($state))
                    ->color(fn (Product $record) => (float) $record->quantity <= (float) $record->min_quantity ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('القسم')
                    ->relationship('category', 'name')
                    ->preload(),

                Tables\Filters\Filter::make('low_stock')
                    ->label('مخزون منخفض')
                    ->query(fn ($query) => $query->whereColumn('quantity', '<=', 'min_quantity')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('فعّال')
                    ->falseLabel('غير فعّال'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
