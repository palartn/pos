<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentSalesWidget extends BaseWidget
{
    protected static ?string $heading = 'آخر الفواتير';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('sales.view') ?? false;
    }

    public function table(Table $table): Table
    {
        $isManager = auth()->user()?->can('reports.view') ?? false;

        return $table
            ->query(
                Sale::query()->with('customer')
                    ->when(! $isManager, fn ($q) => $q->where('user_id', auth()->id()))
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->label('الفاتورة')->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('customer.name')->label('العميل')->placeholder('نقدي'),
                Tables\Columns\TextColumn::make('total')->label('الإجمالي')
                    ->formatStateUsing(fn ($state) => '₪' . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('payment_method')->label('الدفع')
                    ->formatStateUsing(fn ($state) => PaymentMethod::tryFrom($state)?->label() ?? $state),
            ])
            ->paginated([8, 15]);
    }
}
