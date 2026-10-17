<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $navigationLabel = 'وحدات القياس';
    protected static ?string $modelLabel = 'وحدة';
    protected static ?string $pluralModelLabel = 'وحدات القياس';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('اسم الوحدة')
                ->placeholder('مثال: كيلو، قطعة، كرتون')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('symbol')
                ->label('الرمز (اختياري)')
                ->maxLength(20),

            Forms\Components\Toggle::make('allow_decimal')
                ->label('يقبل كميات عشرية (للمنتجات التي تُباع بالوزن)')
                ->helperText('فعّلها لوحدات مثل الكيلو والغرام واللتر. الوحدات الأخرى (قطعة، علبة...) تبقى أعداداً صحيحة.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الوحدة')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('الرمز'),

                Tables\Columns\IconColumn::make('allow_decimal')
                    ->label('عشرية (وزن)')
                    ->boolean(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    ->counts('products')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
