<?php

namespace App\Filament\Resources\CashMovementResource\Pages;

use App\Filament\Resources\CashMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashMovement extends CreateRecord
{
    protected static string $resource = CashMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
