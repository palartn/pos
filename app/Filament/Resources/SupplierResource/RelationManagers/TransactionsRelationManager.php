<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Filament\Resources\SupplierResource;
use App\Models\SupplierTransaction;
use App\Support\Money;
use App\Support\PaymentMethod;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'كشف الحساب';
    protected static ?string $modelLabel = 'حركة';
    protected static ?string $pluralModelLabel = 'الحركات';

    private const TYPE_LABELS = [
        'purchase' => 'فاتورة شراء',
        'payment' => 'دفعة',
        'opening' => 'رصيد سابق',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::TYPE_LABELS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'payment' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('description')
                    ->label('التفاصيل (الأصناف والأسعار)')
                    ->wrap()
                    ->limit(160)
                    ->tooltip(fn (SupplierTransaction $record) => $record->description)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn ($state) => $state ? PaymentMethod::label($state) : '—'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('المرجع')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state) => ((float) $state > 0 ? '+ ' : '− ') . Money::format(abs((float) $state)))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('الرصيد بعد الحركة')
                    ->formatStateUsing(fn ($state) => SupplierResource::balanceLabel($state))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('الموظف')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(self::TYPE_LABELS),
            ]);
    }
}
