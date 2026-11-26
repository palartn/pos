<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\VoucherResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\Customer;
use App\Models\Voucher;
use App\Services\VoucherService;
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

class VoucherResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'vouchers.view';

    protected static ?string $model = Voucher::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?string $modelLabel = 'سند قبض';
    protected static ?string $pluralModelLabel = 'سندات القبض';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return auth()->user()?->can('vouchers.create') ?? false;
    }

    // السندات لا تُعدَّل ولا تُحذف؛ يُلغى السند فقط (للمدير العام) ويبقى رقمه
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
            Forms\Components\Section::make('سند قبض جديد')->schema([
                Forms\Components\Select::make('customer_id')->label('العميل')
                    ->options(fn () => Customer::where('balance', '>', 0)->orderBy('name')->get()
                        ->mapWithKeys(fn ($c) => [$c->id => $c->name . ' — دين: ' . Ledger::money($c->balance)]))
                    ->searchable()->required()->live()
                    ->helperText('تظهر فقط العملاء الذين عليهم دين'),
                ...Ledger::paymentFields(fn (Get $get) => (float) (Customer::find($get('customer_id'))?->balance ?? 0)),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['customer', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('number')->label('رقم السند')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Summarizer::make()->label('إجمالي السندات السارية')
                        ->using(fn ($query) => (float) $query->where('status', 'active')->sum('amount'))
                        ->formatStateUsing(fn ($s) => Ledger::money($s))),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn ($s) => PaymentMethod::tryFrom($s)?->label() ?? $s),
                Tables\Columns\TextColumn::make('payment_details')->label('التفاصيل')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')->label('الدين بعد السند')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($s) => $s === 'active' ? 'ساري' : 'ملغى')
                    ->color(fn ($s) => $s === 'active' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('user.name')->label('أصدره')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['active' => 'ساري', 'cancelled' => 'ملغى']),
                Tables\Filters\SelectFilter::make('customer_id')->label('العميل')->relationship('customer', 'name')->searchable(),
                Tables\Filters\Filter::make('date')->label('الفترة')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
                        ->when($data['until'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('vouchers.show', $record) . '?autoprint=1')
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء السند')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->isCancelled() && (auth()->user()?->can('vouchers.cancel') ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء سند قبض')
                    ->modalDescription('يبقى السند ظاهراً بحالة «ملغى» برقمه، ويعود المبلغ ديناً على العميل. لا يمكن التراجع.')
                    ->form([
                        Forms\Components\Textarea::make('reason')->label('سبب الإلغاء')->required()->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(VoucherService::class)->cancel($record, $data['reason']);
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
            'index' => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
        ];
    }
}
