<?php

namespace App\Filament\Resources\PaymentVoucherResource\Pages;

use App\Filament\Resources\PaymentVoucherResource;
use App\Models\Supplier;
use App\Services\PaymentVoucherService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class CreatePaymentVoucher extends CreateRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PaymentVoucherService::class)->pay(
                Supplier::findOrFail($data['supplier_id']),
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
            ->title('تم إصدار سند الصرف ' . $this->record->number)
            ->actions([
                Action::make('print')->label('طباعة السند')
                    ->url(route('payment-vouchers.show', $this->record) . '?autoprint=1', shouldOpenInNewTab: true),
            ]);
    }
}
