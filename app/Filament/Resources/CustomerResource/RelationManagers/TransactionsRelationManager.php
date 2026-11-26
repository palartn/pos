<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\RelationManagers\LedgerRelationManager;

class TransactionsRelationManager extends LedgerRelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'دفتر الديون (كشف حساب العميل)';

    protected function canPay(): bool
    {
        return auth()->user()?->can('vouchers.create') ?? false;
    }

    protected function balanceLabel(): string
    {
        return 'الدين المستحق على العميل';
    }
}
