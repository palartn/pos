<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Support\Ledger;
use App\Filament\Support\ProductSelect;
use App\Models\Product;
use App\Models\Purchase;
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

class PurchaseResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'purchases.manage';

    protected static ?string $model = Purchase::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'المشتريات';
    protected static ?string $modelLabel = 'فاتورة شراء';
    protected static ?string $pluralModelLabel = 'فواتير الشراء';
    protected static ?int $navigationSort = 1;

    // الفاتورة بعد حفظها لا تُعدَّل ولا تُحذف؛ التصحيح يتم بمرتجع شراء
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
            Forms\Components\Section::make('بيانات الفاتورة')->schema([
                Forms\Components\Select::make('supplier_id')->label('المورد')
                    ->relationship('supplier', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()->preload()->required(),
                Forms\Components\Select::make('warehouse_id')->label('المخزن المستلِم')
                    ->relationship('warehouse', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->default(fn () => Warehouse::defaultOrCreate()->id)->required(),
                Forms\Components\DatePicker::make('purchase_date')->label('تاريخ الفاتورة')->default(now())->required(),
                Forms\Components\TextInput::make('supplier_invoice_no')->label('رقم فاتورة المورد')->maxLength(100),
            ])->columns(4),

            Forms\Components\Section::make('الأصناف')->schema([
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

            Forms\Components\Section::make('الحساب والدفع')->schema([
                Forms\Components\Placeholder::make('subtotal_view')->label('المجموع')
                    ->content(fn (Get $get) => Ledger::money(ProductSelect::linesTotal($get('items')))),
                Forms\Components\TextInput::make('discount')->label('الخصم (₪)')
                    ->numeric()->step(0.01)->minValue(0)->default(0)->live(onBlur: true),
                Forms\Components\Placeholder::make('total_view')->label('الإجمالي')
                    ->content(fn (Get $get) => Ledger::money(
                        max(0, ProductSelect::linesTotal($get('items')) - (is_numeric($get('discount')) ? (float) $get('discount') : 0))
                    )),
                Forms\Components\TextInput::make('paid')->label('المدفوع الآن (₪)')
                    ->numeric()->step(0.01)->minValue(0)->default(0)->live(onBlur: true)
                    ->helperText('اتركه 0 إن كانت الفاتورة آجلة بالكامل'),
                Forms\Components\Select::make('payment_method')->label('طريقة الدفع')
                    ->options(collect(PaymentMethod::cases())
                        ->reject(fn ($m) => $m === PaymentMethod::Credit)
                        ->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all())
                    ->default('cash')->live()
                    ->visible(fn (Get $get) => (float) $get('paid') > 0),
                Forms\Components\TextInput::make('payment_details')->label('تفاصيل الدفع')
                    ->visible(fn (Get $get) => (float) $get('paid') > 0
                        && (PaymentMethod::tryFrom((string) $get('payment_method'))?->needsDetails() ?? false)),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('رقم الفاتورة')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('purchase_date')->label('التاريخ')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
                Tables\Columns\TextColumn::make('supplier_invoice_no')->label('فاتورة المورد')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->formatStateUsing(fn ($state) => Ledger::money($state))->sortable(),
                Tables\Columns\TextColumn::make('paid')->label('المدفوع')->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('due_amount')->label('المتبقي')
                    ->formatStateUsing(fn ($state) => Ledger::money($state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('warehouse.name')->label('المخزن')->toggleable(),
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')->label('المورد')->relationship('supplier', 'name'),
                Tables\Filters\Filter::make('has_due')->label('بها متبقٍّ')->toggle()
                    ->query(fn (Builder $query) => $query->where('due_amount', '>', 0)),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('عرض')]);
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
