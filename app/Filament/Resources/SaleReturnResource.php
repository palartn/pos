<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\SaleReturnResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\SaleReturn;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SaleReturnResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'sales.return';

    protected static ?string $model = SaleReturn::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?string $modelLabel = 'مرتجع بيع';
    protected static ?string $pluralModelLabel = 'مرتجعات البيع';
    protected static ?int $navigationSort = 2;

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['sale', 'customer', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('return_number')->label('رقم المرتجع')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('sale.invoice_number')->label('الفاتورة الأصلية')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->placeholder('عميل نقدي'),
                Tables\Columns\TextColumn::make('total')->label('القيمة')->formatStateUsing(fn ($s) => Ledger::money($s))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('الإجمالي')->formatStateUsing(fn ($s) => Ledger::money($s))),
                Tables\Columns\TextColumn::make('debt_reduced')->label('خُصم من الدين')->formatStateUsing(fn ($s) => Ledger::money($s)),
                Tables\Columns\TextColumn::make('cash_refund')->label('أُعيد للعميل')->formatStateUsing(fn ($s) => Ledger::money($s)),
                Tables\Columns\TextColumn::make('refund_method')->label('طريقة الرد')
                    ->formatStateUsing(fn ($s) => $s ? (PaymentMethod::tryFrom($s)?->label() ?? $s) : '—'),
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->toggleable(),
                Tables\Columns\TextColumn::make('notes')->label('السبب')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSaleReturns::route('/')];
    }
}
