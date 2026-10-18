<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use App\Services\SupplierAccount;
use App\Support\Money;
use App\Support\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'المشتريات';
    protected static ?string $navigationLabel = 'الموردون';
    protected static ?string $modelLabel = 'مورد';
    protected static ?string $pluralModelLabel = 'الموردون';
    protected static ?int $navigationSort = 1;

    /**
     * عدّاد الموردين الذين لهم مبالغ علينا.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Supplier::where('balance', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * وصف الرصيد: موجب = له علينا، سالب = لنا عنده.
     */
    public static function balanceLabel($balance): string
    {
        $value = (float) $balance;

        if ($value > 0) {
            return 'له علينا: ' . Money::format($value);
        }

        if ($value < 0) {
            return 'لنا عنده: ' . Money::format(abs($value));
        }

        return 'الحساب مسدد';
    }

    /**
     * حقول نافذة "دفعة للمورد".
     */
    public static function paymentForm(Supplier $supplier): array
    {
        $balance = (float) $supplier->balance;

        return [
            Forms\Components\Placeholder::make('current_balance')
                ->label('رصيد المورد الحالي')
                ->content(static::balanceLabel($balance)),

            Forms\Components\TextInput::make('amount')
                ->label('المبلغ المدفوع')
                ->numeric()
                ->minValue(0.01)
                ->default($balance > 0 ? $balance : null)
                ->required()
                ->suffix(config('store.currency_symbol', '₪'))
                ->helperText('يمكن دفع مبلغ أكبر من المستحق، فيصبح الفرق دفعة مقدمة لنا عند المورد'),

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

            Forms\Components\Toggle::make('from_drawer')
                ->label('يُخصم من درج الكاشير')
                ->helperText('يتطلب جلسة كاشير مفتوحة. أوقفه إذا دُفع المبلغ من خارج الدرج')
                ->default(true)
                ->visible(fn (Forms\Get $get) => $get('method') === 'cash'),

            Forms\Components\TextInput::make('note')
                ->label('ملاحظة (اختياري)')
                ->maxLength(500),
        ];
    }

    /**
     * تنفيذ تسجيل الدفعة. يرجع true عند النجاح.
     */
    public static function handlePayment(Supplier $supplier, array $data): bool
    {
        try {
            app(SupplierAccount::class)->pay(
                $supplier->id,
                (float) $data['amount'],
                (string) $data['method'],
                isset($data['reference']) ? trim((string) $data['reference']) : null,
                isset($data['note']) ? trim((string) $data['note']) : null,
                (bool) ($data['from_drawer'] ?? true),
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
            Forms\Components\Section::make('بيانات المورد')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('اسم المورد')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('company')
                        ->label('الشركة')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->tel()
                        ->maxLength(30),

                    Forms\Components\TextInput::make('address')
                        ->label('العنوان')
                        ->maxLength(255),

                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعّال')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('الحساب')
                ->schema([
                    Forms\Components\TextInput::make('opening_balance')
                        ->label('رصيد سابق')
                        ->numeric()
                        ->default(0)
                        ->suffix(config('store.currency_symbol', '₪'))
                        ->helperText('اكتب المبلغ المستحق للمورد من قبل (موجب). استخدم قيمة سالبة إذا كان لنا رصيد عنده.')
                        ->visibleOn('create'),

                    Forms\Components\Placeholder::make('balance_label')
                        ->label('الرصيد الحالي')
                        ->content(fn (?Supplier $record) => $record ? static::balanceLabel($record->balance) : '')
                        ->visibleOn('edit'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('المورد')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Supplier $record) => $record->company),

                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),

                Tables\Columns\TextColumn::make('balance')
                    ->label('الرصيد')
                    ->formatStateUsing(fn ($state) => static::balanceLabel($state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : ((float) $state < 0 ? 'success' : 'gray'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('we_owe')
                    ->label('له علينا (علينا دين)')
                    ->query(fn ($query) => $query->where('balance', '>', 0)),

                Tables\Filters\Filter::make('they_owe')
                    ->label('لنا عنده (دفعة مقدمة)')
                    ->query(fn ($query) => $query->where('balance', '<', 0)),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('فعّال')
                    ->falseLabel('غير فعّال'),
            ])
            ->actions([
                Tables\Actions\Action::make('pay')
                    ->label('دفعة للمورد')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('تسجيل دفعة للمورد')
                    ->modalSubmitActionLabel('تسجيل')
                    ->form(fn (Supplier $record) => static::paymentForm($record))
                    ->action(fn (Supplier $record, array $data) => static::handlePayment($record, $data)),

                Tables\Actions\Action::make('purchase')
                    ->label('فاتورة شراء')
                    ->icon('heroicon-o-shopping-bag')
                    ->url(fn (Supplier $record) => PurchaseResource::getUrl('create', ['supplier' => $record->id])),

                Tables\Actions\EditAction::make()->label('كشف الحساب / تعديل'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\PurchasesRelationManager::class,
        ];
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
