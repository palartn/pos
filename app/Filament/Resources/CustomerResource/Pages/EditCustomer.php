<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // لا يُحذف عميل عليه دين
            Actions\DeleteAction::make()->hidden(fn ($record) => (float) $record->balance != 0),
        ];
    }
}
