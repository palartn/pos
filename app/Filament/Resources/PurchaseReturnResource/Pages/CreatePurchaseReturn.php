<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Services\PurchaseService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseService::class)->returnToSupplier($data);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['data.items' => $e->getMessage()]);
        }
    }
}
