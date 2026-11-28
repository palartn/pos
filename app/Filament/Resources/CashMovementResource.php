<?php

namespace App\Filament\Resources;

use App\Enums\CashMovementType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\CashMovementResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\CashierShift;
use App\Models\CashMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CashMovementResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'cashbox.manage';

    protected static ?string $model = CashMovement::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'الخزينة والورديات';
    protected static ?string $modelLabel = 'حركة نقدية';
    protected static ?string $pluralModelLabel = 'المصروفات والإيرادات';
    protected static ?string $navigationLabel = 'المصروفات والإيرادات';
    protected static ?int $navigationSort = 2;

    // الحركات المالية لا تُعدَّل ولا تُحذف
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
            Forms\Components\Section::make('الحركة')->schema([
                Forms\Components\Select::make('type')->label('النوع')
                    ->options([
                        CashMovementType::Expense->value => CashMovementType::Expense->getLabel(),
                        CashMovementType::Income->value => CashMovementType::Income->getLabel(),
                    ])
                    ->default(CashMovementType::Expense->value)->required()->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('category', null)),
                Forms\Components\Select::make('category')->label('التصنيف')
                    ->options(fn (Get $get) => (CashMovementType::tryFrom((string) $get('type')) ?? CashMovementType::Expense)->categories())
                    ->required(),
                Forms\Components\TextInput::make('amount')->label('المبلغ (₪)')
                    ->numeric()->step(0.01)->minValue(0.01)->required(),
                Forms\Components\DatePicker::make('occurred_on')->label('التاريخ')->default(now())->required(),
                Forms\Components\Select::make('shift_id')->label('مصدر النقد')
                    ->options(fn () => CashierShift::where('status', 'open')->with('user')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => 'درج ' . $s->user->name . ' — ' . $s->opened_at->format('Y-m-d H:i')]))
                    ->placeholder('الخزينة الرئيسية (خارج درج الكاشير)')
                    ->default(fn () => CashierShift::openFor(auth()->id())?->id)
                    ->helperText('اختر الدرج إن خرج المبلغ من (أو دخل إلى) درج وردية مفتوحة، فيدخل في حساب الصندوق عند إغلاقها'),
                Forms\Components\TextInput::make('description')->label('البيان')->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['shift.user', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('occurred_on')->label('التاريخ')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('category')->label('التصنيف')
                    ->formatStateUsing(fn ($state) => CashMovementType::categoryLabel($state)),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->weight('bold')
                    ->summarize([
                        Tables\Columns\Summarizers\Summarizer::make()->label('إجمالي الإيرادات')
                            ->using(fn ($query) => (float) $query->where('type', 'income')->sum('amount'))
                            ->formatStateUsing(fn ($s) => Ledger::money($s)),
                        Tables\Columns\Summarizers\Summarizer::make()->label('إجمالي المصروفات')
                            ->using(fn ($query) => (float) $query->where('type', 'expense')->sum('amount'))
                            ->formatStateUsing(fn ($s) => Ledger::money($s)),
                    ]),
                Tables\Columns\TextColumn::make('description')->label('البيان')->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('shift.user.name')->label('الدرج')->placeholder('الخزينة الرئيسية'),
                Tables\Columns\TextColumn::make('user.name')->label('سجّلها')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('النوع')
                    ->options([
                        CashMovementType::Income->value => 'إيراد',
                        CashMovementType::Expense->value => 'مصروف',
                    ]),
                Tables\Filters\Filter::make('date')->label('الفترة')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('occurred_on', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('occurred_on', '<=', $v))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashMovements::route('/'),
            'create' => Pages\CreateCashMovement::route('/create'),
        ];
    }
}
