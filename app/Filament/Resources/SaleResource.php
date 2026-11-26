<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\SaleResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\Sale;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SaleResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'sales.view';

    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?string $modelLabel = 'فاتورة بيع';
    protected static ?string $pluralModelLabel = 'فواتير البيع';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
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

    // الكاشير يرى فواتيره فقط، والمدير/المحاسب يرون الكل
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! (auth()->user()?->can('reports.view') ?? false)) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['customer', 'user'])->withSum('returns', 'total'))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('رقم الفاتورة')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->placeholder('عميل نقدي')->searchable(),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')->formatStateUsing(fn ($s) => Ledger::money($s))->sortable(),
                Tables\Columns\TextColumn::make('paid')->label('المدفوع')->formatStateUsing(fn ($s) => Ledger::money($s)),
                Tables\Columns\TextColumn::make('due_amount')->label('الدين')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))
                    ->color(fn ($s) => (float) $s > 0 ? 'danger' : null),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn ($s) => PaymentMethod::tryFrom($s)?->label() ?? $s),
                Tables\Columns\TextColumn::make('returns_sum_total')->label('المرتجع')
                    ->formatStateUsing(fn ($s) => (float) $s > 0 ? Ledger::money($s) : '—')
                    ->color(fn ($s) => (float) $s > 0 ? 'warning' : null),
                Tables\Columns\TextColumn::make('user.name')->label('الكاشير')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_method')->label('طريقة الدفع')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()),
                Tables\Filters\Filter::make('has_due')->label('بها دين')->toggle()
                    ->query(fn (Builder $q) => $q->where('due_amount', '>', 0)),
                Tables\Filters\Filter::make('date')->label('الفترة')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(fn (Builder $q, array $d) => $q
                        ->when($d['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($d['until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))),
            ])
            ->actions([Tables\Actions\ViewAction::make()->label('عرض')]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $money = fn ($s) => Ledger::money($s);

        return $infolist->schema([
            Infolists\Components\Section::make('الفاتورة')->schema([
                Infolists\Components\TextEntry::make('invoice_number')->label('رقم الفاتورة')->weight('bold'),
                Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
                Infolists\Components\TextEntry::make('user.name')->label('الكاشير')->placeholder('—'),
                Infolists\Components\TextEntry::make('customer.name')->label('العميل')->placeholder('عميل نقدي'),
                Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn ($s) => PaymentMethod::tryFrom($s)?->label() ?? $s),
                Infolists\Components\TextEntry::make('payment_details')->label('تفاصيل الدفع')->placeholder('—'),
                Infolists\Components\TextEntry::make('price_level')->label('مستوى السعر')
                    ->formatStateUsing(fn ($s) => $s === 'wholesale' ? 'جملة' : 'تجزئة'),
            ])->columns(3),

            Infolists\Components\Section::make('الأصناف')->schema([
                Infolists\Components\RepeatableEntry::make('items')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('product_name')->label('الصنف'),
                    Infolists\Components\TextEntry::make('quantity')->label('الكمية')
                        ->formatStateUsing(fn ($s) => rtrim(rtrim(number_format((float) $s, 3, '.', ''), '0'), '.')),
                    Infolists\Components\TextEntry::make('unit_price')->label('السعر')->formatStateUsing($money),
                    Infolists\Components\TextEntry::make('total')->label('الإجمالي')->formatStateUsing($money),
                ])->columns(4),
            ]),

            Infolists\Components\Section::make('الحساب')->schema([
                Infolists\Components\TextEntry::make('subtotal')->label('المجموع')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('discount')->label('الخصم')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('total')->label('الإجمالي')->formatStateUsing($money)->weight('bold'),
                Infolists\Components\TextEntry::make('paid')->label('المدفوع')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('change_amount')->label('الباقي للعميل')->formatStateUsing($money),
                Infolists\Components\TextEntry::make('due_amount')->label('الدين')->formatStateUsing($money),
            ])->columns(3),

            Infolists\Components\Section::make('المرتجعات')->schema([
                Infolists\Components\RepeatableEntry::make('returns')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('return_number')->label('رقم المرتجع'),
                    Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
                    Infolists\Components\TextEntry::make('total')->label('القيمة')->formatStateUsing($money),
                    Infolists\Components\TextEntry::make('debt_reduced')->label('خُصم من الدين')->formatStateUsing($money),
                    Infolists\Components\TextEntry::make('cash_refund')->label('أُعيد للعميل')->formatStateUsing($money),
                ])->columns(5),
            ])->visible(fn ($record) => $record->returns()->exists()),
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
