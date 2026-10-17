<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Support\Money;
use App\Support\PaymentMethod;
use App\Support\Qty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?string $navigationLabel = 'الفواتير';
    protected static ?string $modelLabel = 'فاتورة';
    protected static ?string $pluralModelLabel = 'الفواتير';
    protected static ?int $navigationSort = 4;

    private const STATUS_LABELS = [
        'completed' => 'مكتملة',
        'refunded' => 'مرتجعة',
        'partial_refund' => 'مرتجع جزئي',
    ];

    private const STATUS_COLORS = [
        'completed' => 'success',
        'refunded' => 'danger',
        'partial_refund' => 'warning',
    ];

    // الفواتير تُنشأ من نقطة البيع فقط
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'user', 'payments']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('بيانات الفاتورة')
                ->schema([
                    Infolists\Components\TextEntry::make('invoice_number')
                        ->label('رقم الفاتورة')
                        ->copyable(),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('التاريخ')
                        ->dateTime('Y-m-d H:i'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('الحالة')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::STATUS_LABELS[$state] ?? $state)
                        ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),

                    Infolists\Components\TextEntry::make('customer.name')
                        ->label('العميل')
                        ->placeholder('عميل نقدي'),

                    Infolists\Components\TextEntry::make('user.name')
                        ->label('الكاشير'),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('ملاحظات')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])
                ->columns(3),

            Infolists\Components\Section::make('الأصناف')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product.name')
                                ->label('الصنف')
                                ->placeholder('منتج محذوف'),

                            Infolists\Components\TextEntry::make('quantity')
                                ->label('الكمية')
                                ->formatStateUsing(fn ($state) => Qty::format($state)),

                            Infolists\Components\TextEntry::make('unit_price')
                                ->label('سعر الوحدة')
                                ->formatStateUsing(fn ($state) => Money::format($state)),

                            Infolists\Components\TextEntry::make('total')
                                ->label('الإجمالي')
                                ->formatStateUsing(fn ($state) => Money::format($state)),
                        ])
                        ->columns(4),
                ]),

            Infolists\Components\Section::make('المبالغ')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')
                        ->label('المجموع')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('discount')
                        ->label('الخصم')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('total')
                        ->label('الإجمالي')
                        ->weight('bold')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('paid')
                        ->label('المدفوع')
                        ->formatStateUsing(fn ($state) => Money::format($state)),

                    Infolists\Components\TextEntry::make('remaining')
                        ->label('المتبقي')
                        ->state(fn (Sale $record) => Money::format(max(0, round((float) $record->total - (float) $record->paid, 2)))),
                ])
                ->columns(5),

            Infolists\Components\Section::make('الدفع')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('payments')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('method')
                                ->label('الطريقة')
                                ->formatStateUsing(fn ($state) => PaymentMethod::label($state)),

                            Infolists\Components\TextEntry::make('amount')
                                ->label('المبلغ')
                                ->formatStateUsing(fn ($state) => Money::format($state)),

                            Infolists\Components\TextEntry::make('reference')
                                ->label('رقم العملية / التحديد')
                                ->placeholder('—'),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('العميل')
                    ->placeholder('عميل نقدي')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('الكاشير')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('payment_methods')
                    ->label('طريقة الدفع')
                    ->state(fn (Sale $record) => $record->payments
                        ->map(fn ($p) => PaymentMethod::label($p->method))
                        ->unique()
                        ->implode('، ')),

                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('المتبقي')
                    ->state(fn (Sale $record) => max(0, round((float) $record->total - (float) $record->paid, 2)))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::options())
                    ->query(function (Builder $query, array $data): Builder {
                        return filled($data['value'] ?? null)
                            ? $query->whereHas('payments', fn (Builder $q) => $q->where('method', $data['value']))
                            : $query;
                    }),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('الكاشير')
                    ->relationship('user', 'name'),

                Tables\Filters\SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable(),

                Tables\Filters\Filter::make('unpaid')
                    ->label('فيها مبلغ متبقٍ (آجل)')
                    ->query(fn (Builder $query) => $query->whereColumn('paid', '<', 'total')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),

                Tables\Actions\Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Sale $record) => route('receipts.show', ['sale' => $record, 'print' => 1]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSales::route('/'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
