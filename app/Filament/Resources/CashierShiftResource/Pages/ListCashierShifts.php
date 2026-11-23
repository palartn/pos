<?php

namespace App\Filament\Resources\CashierShiftResource\Pages;

use App\Filament\Resources\CashierShiftResource;
use App\Models\CashierShift;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCashierShifts extends ListRecords
{
    protected static string $resource = CashierShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('فتح وردية')
                ->icon('heroicon-o-lock-open')
                ->visible(fn () => ! CashierShift::openFor(auth()->id()))
                ->modalHeading('فتح وردية جديدة')
                ->form([
                    Forms\Components\TextInput::make('opening_cash')->label('النقد الموجود في الدرج عند الفتح (₪)')
                        ->numeric()->step(0.01)->minValue(0)->required()
                        ->default(fn () => (float) (CashierShift::where('status', 'closed')->latest('closed_at')->value('counted_cash') ?? 0))
                        ->helperText('يُعبَّأ تلقائياً بالمبلغ المعدود عند إغلاق آخر وردية'),
                ])
                ->action(function (array $data) {
                    if (CashierShift::openFor(auth()->id())) {
                        Notification::make()->title('لديك وردية مفتوحة بالفعل')->warning()->send();

                        return;
                    }

                    CashierShift::create([
                        'user_id' => auth()->id(),
                        'opened_at' => now(),
                        'opening_cash' => $data['opening_cash'],
                        'status' => 'open',
                    ]);

                    Notification::make()->title('تم فتح الوردية، يمكنك البدء بالبيع')->success()->send();
                }),
        ];
    }
}
