<?php

namespace App\Filament\Resources\ProductStockResource\Pages;

use App\Filament\Resources\ProductStockResource;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListProductStocks extends ListRecords
{
    protected static string $resource = ProductStockResource::class;

    private static function productSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('product_id')
            ->label('الصنف')
            ->searchable()
            ->required()
            ->getSearchResultsUsing(fn (string $search) => Product::query()
                ->where('name', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->limit(30)->pluck('name', 'id'))
            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name);
    }

    protected function getHeaderActions(): array
    {
        $warehouses = fn () => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id');

        return [
            Actions\Action::make('adjust')
                ->label('إدخال / تسوية رصيد')
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('إدخال أو تسوية رصيد صنف')
                ->form([
                    self::productSelect(),
                    Forms\Components\Select::make('warehouse_id')->label('المخزن')
                        ->options($warehouses)->default(fn () => Warehouse::defaultOrCreate()->id)->required(),
                    Forms\Components\Select::make('mode')->label('نوع العملية')->required()->default('in')
                        ->options([
                            'in' => 'إضافة كمية (رصيد افتتاحي / استلام)',
                            'out' => 'خصم كمية (تلف / فقد)',
                            'set' => 'ضبط الرصيد إلى كمية محددة (جرد)',
                        ]),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')
                        ->numeric()->step(0.001)->minValue(0)->required(),
                    Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                ])
                ->action(function (array $data) {
                    $qty = (float) $data['quantity'];
                    $service = app(StockService::class);

                    try {
                        if ($data['mode'] === 'set') {
                            $current = (float) (ProductStock::where('product_id', $data['product_id'])
                                ->where('warehouse_id', $data['warehouse_id'])->value('quantity') ?? 0);
                            $diff = round($qty - $current, 3);

                            if ($diff == 0.0) {
                                Notification::make()->title('الرصيد مطابق، لا حاجة للتسوية')->warning()->send();

                                return;
                            }

                            $service->adjust($data['product_id'], $data['warehouse_id'], $diff, 'adjustment', 'ضبط الرصيد (جرد)', $data['notes'] ?? null);
                        } elseif ($data['mode'] === 'out') {
                            $service->adjust($data['product_id'], $data['warehouse_id'], -$qty, 'adjustment', 'خصم كمية', $data['notes'] ?? null);
                        } else {
                            $service->adjust($data['product_id'], $data['warehouse_id'], $qty, 'opening', 'إدخال كمية', $data['notes'] ?? null);
                        }
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('تم تحديث الرصيد')->success()->send();
                }),

            Actions\Action::make('transfer')
                ->label('تحويل بين المخازن')
                ->icon('heroicon-o-arrows-right-left')
                ->color('gray')
                ->form([
                    self::productSelect(),
                    Forms\Components\Select::make('from')->label('من مخزن')->options($warehouses)->required()
                        ->default(fn () => Warehouse::defaultOrCreate()->id),
                    Forms\Components\Select::make('to')->label('إلى مخزن')->options($warehouses)->required(),
                    Forms\Components\TextInput::make('quantity')->label('الكمية')
                        ->numeric()->step(0.001)->minValue(0.001)->required(),
                    Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                ])
                ->action(function (array $data) {
                    try {
                        app(StockService::class)->transfer(
                            $data['product_id'], $data['from'], $data['to'], (float) $data['quantity'], $data['notes'] ?? null
                        );
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title('تم التحويل')->success()->send();
                }),
        ];
    }
}
