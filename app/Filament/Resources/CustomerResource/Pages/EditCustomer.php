<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('receivePayment')
                ->label('تسجيل دفعة')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => (float) $this->record->balance > 0)
                ->modalHeading('تسجيل دفعة من العميل')
                ->modalSubmitActionLabel('تسجيل')
                ->form(fn () => CustomerResource::paymentForm($this->record))
                ->action(function (array $data) {
                    if (CustomerResource::handlePayment($this->record, $data)) {
                        $this->record->refresh();
                        $this->refreshFormData(['balance']);
                    }
                }),

            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->sales()->exists() || $this->record->transactions()->exists()) {
                        Notification::make()
                            ->title('لا يمكن حذف العميل لأن لديه فواتير أو حركات مسجلة.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
