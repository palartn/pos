<?php

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Filament\Resources\StockTakeResource;
use App\Services\StockTakeService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewStockTake extends ViewRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('اعتماد الجرد وتسوية الأرصدة')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('اعتماد الجرد')
                ->modalDescription(fn () => 'سيتم تعديل أرصدة الأصناف التي بها فروقات. الأصناف غير المجرودة ('
                    . $this->record->items()->whereNull('counted_qty')->count()
                    . ') لن تتغير أرصدتها. لا يمكن التراجع بعد الاعتماد.')
                ->action(function () {
                    try {
                        $value = app(StockTakeService::class)->approve($this->record);
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('تم اعتماد الجرد وتسوية الأرصدة')
                        ->body('قيمة الفروقات بسعر التكلفة: ₪' . number_format($value, 2))
                        ->success()->send();

                    $this->record->refresh();
                }),
        ];
    }
}
