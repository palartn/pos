<?php

namespace App\Filament\RelationManagers;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Filament\Support\Ledger;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** كشف حساب (دفتر ديون) مشترك بين العملاء والموردين */
abstract class LedgerRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'كشف الحساب';

    protected function debtLabel(): string
    {
        return 'تسجيل دين';
    }

    protected function balanceLabel(): string
    {
        return 'الرصيد الحالي';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('created_at', 'desc')
            ->description(fn () => $this->balanceLabel() . ': ' . Ledger::money($this->getOwnerRecord()->balance))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('description')->label('البند / الوصف')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($state) => Ledger::money($state))->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')->label('طريقة الدفع')
                    ->formatStateUsing(fn ($state) => $state ? (PaymentMethod::tryFrom($state)?->label() ?? $state) : '—'),
                Tables\Columns\TextColumn::make('payment_details')->label('التفاصيل')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')->label('الرصيد بعد العملية')
                    ->formatStateUsing(fn ($state) => Ledger::money($state)),
                Tables\Columns\TextColumn::make('user.name')->label('المستخدم')->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('النوع')
                    ->options([
                        LedgerType::Debt->value => LedgerType::Debt->getLabel(),
                        LedgerType::Payment->value => LedgerType::Payment->getLabel(),
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('debt')
                    ->label($this->debtLabel())
                    ->icon('heroicon-o-plus-circle')
                    ->color('danger')
                    ->modalHeading($this->debtLabel())
                    ->form(Ledger::debtFields())
                    ->action(fn (array $data) => Ledger::post($this->getOwnerRecord(), LedgerType::Debt, $data)),

                Tables\Actions\Action::make('payment')
                    ->label('تسجيل دفعة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('تسجيل دفعة')
                    ->disabled(fn () => (float) $this->getOwnerRecord()->balance <= 0)
                    ->form(Ledger::paymentFields(fn () => (float) $this->getOwnerRecord()->balance))
                    ->action(fn (array $data) => Ledger::post($this->getOwnerRecord(), LedgerType::Payment, $data)),
            ])
            ->paginated([10, 25, 50]);
    }
}
