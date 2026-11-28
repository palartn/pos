<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PaymentVoucherResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Services\PaymentVoucherService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class PaymentVoucherResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'payvouchers.view';

    protected static ?string $model = PaymentVoucher::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'المشتريات';
    protected static ?string $modelLabel = 'سند صرف';
    protected static ?string $pluralModelLabel = 'سندات الصرف';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return auth()->user()?->can('payvouchers.create') ?? false;
    }

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! (auth()->user()?->can('reports.view') ?? false)) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('سند صرف جديد (دفعة لمورد)')->schema([
                Forms\Components\Select::make('supplier_id')->label('المورد')
                    ->options(fn () => Supplier::where('balance', '>', 0)->orderBy('name')->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->name . ' — مستحق: ' . Ledger::money($s->balance)]))
                    ->searchable()->required()->live()
                    ->helperText('تظهر فقط الموردون الذين لهم مستحقات'),
                ...Ledger::paymentFields(fn (Get $get) => (float) (Supplier::find($get('supplier_id'))?->balance ?? 0)),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q
                ->setModel(new PaymentVoucher)
                ->with(['supplier', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('رقم السند')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('supplier.name')->label('المورد')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()->label('إجمالي السندات السارية')
                        ->using(fn ($query) => (float) $query->where('status', 'active')->sum('amount'))
                        ->formatStateUsing(fn ($s) => Ledger::money($s))),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn ($s) => PaymentMethod::tryFrom($s)?->label() ?? $s),
                Tables\Columns\TextColumn::make('payment_details')->label('التفاصيل')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')->label('المستحق بعد السند')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($s) => $s === 'active' ? 'ساري' : 'ملغى')
                    ->color(fn ($s) => $s === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('user.name')->label('أصدره')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['active' => 'ساري', 'cancelled' => 'ملغى']),
                Tables\Filters\SelectFilter::make('supplier_id')->label('المورد')->relationship('supplier', 'name')->searchable(),
                Tables\Filters\Filter::make('date')->label('الفترة')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('payment-vouchers.show', $record) . '?autoprint=1')
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء السند')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->isCancelled() && (auth()->user()?->can('payvouchers.cancel') ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء سند صرف')
                    ->modalDescription('يبقى السند ظاهراً بحالة «ملغى» برقمه، ويعود المبلغ مستحقاً للمورد. لا يمكن التراجع.')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الإلغاء')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(PaymentVoucherService::class)->cancel($record, $data['reason']);
                        } catch (RuntimeException|InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('تم إلغاء السند ' . $record->number)->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentVouchers::route('/'),
            'create' => Pages\CreatePaymentVoucher::route('/create'),
        ];
    }
}
