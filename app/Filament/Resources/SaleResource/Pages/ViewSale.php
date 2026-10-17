<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('طباعة الإيصال')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('receipts.show', ['sale' => $this->record, 'print' => 1]))
                ->openUrlInNewTab(),
        ];
    }
}
