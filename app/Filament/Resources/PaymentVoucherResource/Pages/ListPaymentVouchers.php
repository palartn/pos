<?php

namespace App\Filament\Resources\PaymentVoucherResource\Pages;

use App\Filament\Resources\PaymentVoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentVouchers extends ListRecords
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('سند صرف جديد')];
    }
}
