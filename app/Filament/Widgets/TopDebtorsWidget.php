<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopDebtorsWidget extends BaseWidget
{
    protected static ?string $heading = 'أكبر ديون العملاء';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('customers.manage') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Customer::query()->where('balance', '>', 0)->orderByDesc('balance'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('العميل')->weight('bold'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')->label('الدين')
                    ->formatStateUsing(fn ($state) => '₪' . number_format((float) $state, 2))->color('danger'),
            ])
            ->emptyStateHeading('لا توجد ديون مستحقة')
            ->paginated([5, 10]);
    }
}
