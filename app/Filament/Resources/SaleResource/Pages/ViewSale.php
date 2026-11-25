<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Filament\Support\Ledger;
use App\Services\SaleReturnService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('طباعة الفاتورة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('receipts.show', $this->record) . '?autoprint=1')
                ->openUrlInNewTab(),

            Actions\Action::make('return')
                ->label('إرجاع أصناف')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => (auth()->user()?->can('sales.return') ?? false)
                    && app(SaleReturnService::class)->returnableItems($this->record)->contains(fn ($i) => $i['remaining'] > 0))
                ->modalHeading('مرتجع بيع')
                ->modalDescription('أدخل الكمية المرتجعة لكل صنف. يُخصم المبلغ من دين العميل أولاً (إن وُجد) والباقي يُرد له.')
                ->fillForm(fn () => [
                    'items' => app(SaleReturnService::class)->returnableItems($this->record)
                        ->filter(fn ($i) => $i['remaining'] > 0)
                        ->map(fn ($i) => $i + ['quantity' => 0])
                        ->values()->all(),
                ])
                ->form([
                    Forms\Components\Repeater::make('items')
                        ->hiddenLabel()
                        ->addable(false)->deletable(false)->reorderable(false)
                        ->schema([
                            Forms\Components\Hidden::make('sale_item_id'),
                            Forms\Components\Hidden::make('product_name'),
                            Forms\Components\Hidden::make('remaining'),
                            Forms\Components\Placeholder::make('label')->hiddenLabel()
                                ->content(fn (Get $get) => $get('product_name') . ' — المتاح للإرجاع: '
                                    . rtrim(rtrim(number_format((float) $get('remaining'), 3, '.', ''), '0'), '.'))
                                ->columnSpan(2),
                            Forms\Components\TextInput::make('quantity')->label('الكمية المرتجعة')
                                ->numeric()->step(0.001)->minValue(0)
                                ->maxValue(fn (Get $get) => (float) $get('remaining'))
                                ->default(0),
                        ])->columns(3),
                    Forms\Components\Textarea::make('notes')->label('سبب الإرجاع')->rows(2),
                ])
                ->action(function (array $data) {
                    $lines = collect($data['items'] ?? [])
                        ->mapWithKeys(fn ($i) => [(int) $i['sale_item_id'] => (float) ($i['quantity'] ?? 0)])
                        ->all();

                    try {
                        $return = app(SaleReturnService::class)->process($this->record, $lines, $data['notes'] ?? null);
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('تم تسجيل المرتجع ' . $return->return_number)
                        ->body('القيمة ' . Ledger::money($return->total)
                            . ($return->debt_reduced > 0 ? ' — خُصم من الدين ' . Ledger::money($return->debt_reduced) : '')
                            . ($return->cash_refund > 0 ? ' — يُرد للعميل ' . Ledger::money($return->cash_refund) : ''))
                        ->success()->send();

                    $this->record->refresh();
                }),
        ];
    }
}
