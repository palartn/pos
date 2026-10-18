<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\PurchaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PurchaseService::class)->create($data);
        } catch (RuntimeException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            // إيقاف الحفظ مع بقاء البيانات في النموذج
            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم تسجيل فاتورة الشراء وتحديث المخزون وحساب المورد';
    }
}
