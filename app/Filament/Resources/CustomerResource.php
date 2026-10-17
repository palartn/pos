<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Services\CustomerAccount;
use App\Support\Money;
use App\Support\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?string $navigationLabel = 'العملاء';
    protected static ?string $modelLabel = 'عميل';
    protected static ?string $pluralModelLabel = 'العملاء';
    protected static ?int $navigationSort = 3;

    /**
     * عدّاد العملاء الذين عليهم ديون.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Customer::where('balance', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * حقول نافذة "تسجيل دفعة".
     */
    public static function paymentForm(Customer $customer): array
    {
        $balance = (float) $customer->balance;

        return [
            Forms\Components\Placeholder::make('current_balance')
                ->label('الرصيد المستحق')
                ->content(Money::format($balance)),

            Forms\Components\TextInput::make('amount')
                ->label('المبلغ المدفوع')
                ->numeric()
                ->minValue(0.01)
                ->maxValue($balance)
                ->default($balance)
                ->required()
                ->suffix(config('store.currency_symbol', '₪')),

            Forms\Components\Select::make('method')
                ->label('طريقة الدفع')
                ->options(collect(PaymentMethod::options())->except('credit')->all())
                ->default('cash')
                ->required()
                ->live(),

            Forms\Components\TextInput::make('reference')
                ->label(fn (Forms\Get $get) => PaymentMethod::detailsLabel((string) $get('method')))
                ->visible(fn (Forms\Get $get) => PaymentMethod::needsDetails((string) $get('method')))
                ->required(fn (Forms\Get $get) => PaymentMethod::detailsRequired((string) $get('method')))
                ->maxLength(255),

            Forms\Components\TextInput::make('note')
                ->label('ملاحظة (اختياري)')
                ->maxLength(500),
        ];
    }

    /**
     * تنفيذ تسجيل الدفعة. يرجع true عند النجاح.
     */
    public static function handlePayment(Customer $customer, array $data): bool
    {
        try {
            app(CustomerAccount::class)->receivePayment(
                $customer->id,
                (float) $data['amount'],
                (string) $data['method'],
                isset($data['reference']) ? trim((string) $data['reference']) : null,
                isset($data['note']) ? trim((string) $data['note']) : null,
            );
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title('تم تسجيل الدفعة')->success()->send();

        return true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('اسم العميل')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->label('رقم الهاتف')
                ->tel()
                ->maxLength(30),

            Forms\Components\TextInput::make('balance')
                ->label('الرصيد المستحق على العميل')
                ->suffix(config('store.currency_symbol', '₪'))
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit')
                ->helperText('للقراءة فقط. يتغير بالبيع الآجل وتسجيل الدفعات'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('الرصيد المستحق')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('has_debt')
                    ->label('عليهم ديون')
                    ->query(fn ($query) => $query->where('balance', '>', 0)),
            ])
            ->actions([
                Tables\Actions\Action::make('receivePayment')
                    ->label('تسجيل دفعة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Customer $record) => (float) $record->balance > 0)
                    ->modalHeading('تسجيل دفعة من العميل')
                    ->modalSubmitActionLabel('تسجيل')
                    ->form(fn (Customer $record) => static::paymentForm($record))
                    ->action(fn (Customer $record, array $data) => static::handlePayment($record, $data)),

                Tables\Actions\EditAction::make()->label('كشف الحساب / تعديل'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\SalesRelationManager::class,
        ];
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
