<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\RelationManagers\LedgerRelationManager;

class TransactionsRelationManager extends LedgerRelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'دفتر الديون (كشف حساب العميل)';

    protected function balanceLabel(): string
    {
        return 'الدين المستحق على العميل';
    }
}
