<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay')
                ->label('دفعة للمورد')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalHeading('تسجيل دفعة للمورد')
                ->modalSubmitActionLabel('تسجيل')
                ->form(fn () => SupplierResource::paymentForm($this->record))
                ->action(function (array $data) {
                    if (SupplierResource::handlePayment($this->record, $data)) {
                        $this->record->refresh();
                        $this->refreshFormData(['balance']);
                    }
                }),

            Actions\Action::make('purchase')
                ->label('فاتورة شراء')
                ->icon('heroicon-o-shopping-bag')
                ->url(fn () => PurchaseResource::getUrl('create', ['supplier' => $this->record->id])),

            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->purchases()->exists() || $this->record->transactions()->exists()) {
                        Notification::make()
                            ->title('لا يمكن حذف المورد لأن لديه فواتير أو حركات مسجلة. عطّله بدلاً من ذلك.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
