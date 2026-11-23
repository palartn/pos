<?php

namespace App\Filament\Resources;

use App\Enums\LedgerType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Filament\Support\Ledger;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'suppliers.manage';

    protected static ?string $model = Supplier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'العملاء والموردون';
    protected static ?string $modelLabel = 'مورد';
    protected static ?string $pluralModelLabel = 'الموردون';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات المورد')->schema([
                Forms\Components\TextInput::make('name')->label('اسم المورد / المندوب')->required()->maxLength(255),
                Forms\Components\TextInput::make('company')->label('الشركة')->maxLength(255),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
                Forms\Components\TextInput::make('address')->label('العنوان')->maxLength(255),
                Forms\Components\Toggle::make('is_active')->label('فعّال')->default(true),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('المورد')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('company')->label('الشركة')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')->label('المستحق للمورد')->sortable()
                    ->formatStateUsing(fn ($state) => Ledger::money($state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_balance')->label('له مستحقات')->toggle()
                    ->query(fn (Builder $query) => $query->where('balance', '>', 0)),
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')
                    ->trueLabel('فعّال')->falseLabel('غير فعّال'),
            ])
            ->actions([
                Tables\Actions\Action::make('payment')
                    ->label('دفعة للمورد')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record) => (float) $record->balance > 0)
                    ->form(Ledger::paymentFields(fn ($record) => (float) $record->balance))
                    ->action(fn ($record, array $data) => Ledger::post($record, LedgerType::Payment, $data)),
                Tables\Actions\EditAction::make()->label('فتح / تعديل'),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\TransactionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
