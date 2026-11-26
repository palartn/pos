<?php

namespace App\Filament\Resources\VoucherResource\Pages;

use App\Filament\Resources\VoucherResource;
use App\Models\Customer;
use App\Services\VoucherService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(VoucherService::class)->receive(
                Customer::findOrFail($data['customer_id']),
                (float) $data['amount'],
                $data['payment_method'] ?? 'cash',
                $data['payment_details'] ?? null,
                $data['description'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['data.amount' => $e->getMessage()]);
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('تم إصدار سند القبض ' . $this->record->number)
            ->actions([
                Action::make('print')->label('طباعة السند')
                    ->url(route('vouchers.show', $this->record) . '?autoprint=1', shouldOpenInNewTab: true),
            ]);
    }
}
