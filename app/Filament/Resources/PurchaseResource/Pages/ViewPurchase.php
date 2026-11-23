<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

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
