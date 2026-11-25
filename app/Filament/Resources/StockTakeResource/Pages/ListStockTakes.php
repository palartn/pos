<?php

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Filament\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockTakes extends ListRecords
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('بدء جرد جديد')];
    }
}
