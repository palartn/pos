<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Filament\RelationManagers\LedgerRelationManager;

class TransactionsRelationManager extends LedgerRelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'كشف حساب المورد';

    protected function debtLabel(): string
    {
        return 'تسجيل مستحق (فاتورة شراء)';
    }

    protected function canPay(): bool
    {
        return auth()->user()?->can('payvouchers.create') ?? false;
    }

    protected function balanceLabel(): string
    {
        return 'المستحق للمورد';
    }
}
