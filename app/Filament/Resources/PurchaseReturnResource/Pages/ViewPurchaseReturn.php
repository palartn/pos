<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReturn extends ViewRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items'] = $this->record->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'quantity' => (float) $i->quantity,
            'unit_cost' => (float) $i->unit_cost,
        ])->all();

        return $data;
    }
}
