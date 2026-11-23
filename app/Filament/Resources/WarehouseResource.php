<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'inventory.manage';

    protected static ?string $model = Warehouse::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'المخزون';
    protected static ?string $modelLabel = 'مخزن';
    protected static ?string $pluralModelLabel = 'المخازن';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('اسم المخزن')->required()->maxLength(255),
            Forms\Components\TextInput::make('location')->label('الموقع')->maxLength(255),
            Forms\Components\Toggle::make('is_default')->label('المخزن الافتراضي (يُباع منه في شاشة البيع)'),
            Forms\Components\Toggle::make('is_active')->label('فعّال')->default(true),
            Forms\Components\Textarea::make('notes')->label('ملاحظات'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('المخزن')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('location')->label('الموقع')->placeholder('—'),
                Tables\Columns\TextColumn::make('stocks_count')->label('عدد الأصناف')->counts('stocks'),
                Tables\Columns\IconColumn::make('is_default')->label('افتراضي')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // لا يُحذف المخزن الافتراضي أو الذي فيه رصيد
                Tables\Actions\DeleteAction::make()->hidden(
                    fn ($record) => $record->is_default || $record->stocks()->where('quantity', '!=', 0)->exists()
                ),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageWarehouses::route('/')];
    }
}
