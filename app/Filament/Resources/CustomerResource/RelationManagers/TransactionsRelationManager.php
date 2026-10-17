<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerTransaction;
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
                    ->formatStateUsing(fn ($state) => $state === 'payment' ? 'دفعة' : 'دين')
                    ->color(fn ($state) => $state === 'payment' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('description')
                    ->label('التفاصيل (الأصناف والأسعار)')
                    ->wrap()
                    ->limit(160)
                    ->tooltip(fn (CustomerTransaction $record) => $record->description),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn ($state) => $state ? PaymentMethod::label($state) : '—'),

                Tables\Columns\TextColumn::make('reference')
                    ->label('المرجع')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state, CustomerTransaction $record) => ($record->type === 'payment' ? '− ' : '+ ') . Money::format($state))
                    ->color(fn (CustomerTransaction $record) => $record->type === 'payment' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('الرصيد بعد الحركة')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('الموظف')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'debt' => 'ديون',
                        'payment' => 'دفعات',
                    ]),
            ]);
    }
}
