<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'products.manage';

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $modelLabel = 'صنف';
    protected static ?string $pluralModelLabel = 'الأصناف';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الصنف')->schema([
                Forms\Components\TextInput::make('name')->label('اسم الصنف')->required()->maxLength(255),
                Forms\Components\TextInput::make('barcode')->label('الباركود')->unique(ignoreRecord: true)->maxLength(255),
                Forms\Components\Select::make('category_id')->label('التصنيف')
                    ->relationship('category', 'name')->searchable()->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('اسم التصنيف')->required()->unique('categories', 'name'),
                    ]),
                Forms\Components\TextInput::make('unit')->label('وحدة القياس')->default('قطعة')->required(),
                Forms\Components\Toggle::make('sold_by_weight')->label('يُباع بالوزن (كميات عشرية)'),
                Forms\Components\Toggle::make('is_active')->label('فعّال')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('الأسعار والتنبيه')->schema([
                Forms\Components\TextInput::make('cost_price')->label('سعر التكلفة (₪)')->numeric()->step(0.01)->minValue(0)->default(0)->required(),
                Forms\Components\TextInput::make('retail_price')->label('سعر التجزئة (₪)')->numeric()->step(0.01)->minValue(0)->required(),
                Forms\Components\TextInput::make('wholesale_price')->label('سعر الجملة (₪)')->numeric()->step(0.01)->minValue(0)
                    ->lte('retail_price')
                    ->helperText('اتركه فارغاً ليُباع لعملاء الجملة بسعر التجزئة'),
                Forms\Components\TextInput::make('min_stock')->label('حد التنبيه (الحد الأدنى)')->numeric()->step(0.001)->minValue(0)->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الصنف')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('barcode')->label('الباركود')->searchable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('category.name')->label('التصنيف')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('retail_price')->label('التجزئة')->sortable()
                    ->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('stock')->label('إجمالي المخزون')->sortable()
                    ->formatStateUsing(fn ($state, $record) => rtrim(rtrim(number_format((float) $state, 3, '.', ''), '0'), '.') . ' ' . $record->unit)
                    ->color(fn ($record) => $record->isLowStock() ? 'danger' : null),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('التصنيف')->relationship('category', 'name'),
                Tables\Filters\Filter::make('low_stock')->label('تحت حد التنبيه')->toggle()
                    ->query(fn (Builder $q) => $q->whereColumn('stock', '<=', 'min_stock')),
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')->trueLabel('فعّال')->falseLabel('غير فعّال'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageProducts::route('/')];
    }
}
