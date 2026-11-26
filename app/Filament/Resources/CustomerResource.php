<?php

namespace App\Filament\Resources;

use App\Enums\LedgerType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Support\Ledger;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'customers.manage';

    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'العملاء والموردون';
    protected static ?string $modelLabel = 'عميل';
    protected static ?string $pluralModelLabel = 'العملاء';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات العميل')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
                Forms\Components\TextInput::make('address')->label('العنوان')->maxLength(255),
                Forms\Components\TextInput::make('credit_limit')->label('سقف الدين (₪)')
                    ->numeric()->minValue(0)->default(0)->helperText('0 = بدون سقف'),
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
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')->label('الدين المستحق')->sortable()
                    ->formatStateUsing(fn ($state) => Ledger::money($state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('credit_limit')->label('سقف الدين')
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? Ledger::money($state) : 'بدون سقف')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_debt')->label('عليهم دين')->toggle()
                    ->query(fn (Builder $q) => $q->where('balance', '>', 0)),
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')
                    ->trueLabel('فعّال')->falseLabel('غير فعّال'),
            ])
            ->actions([
                Tables\Actions\Action::make('payment')
                    ->label('تسجيل دفعة')
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
