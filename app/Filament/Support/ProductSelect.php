<?php

namespace App\Filament\Support;

use App\Models\Product;
use Filament\Forms\Components\Select;

class ProductSelect
{
    public static function make(string $name = 'product_id'): Select
    {
        return Select::make($name)
            ->label('الصنف')
            ->searchable()
            ->required()
            ->getSearchResultsUsing(fn (string $search) => Product::query()
                ->where('name', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->limit(30)->pluck('name', 'id'))
            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name);
    }

    /** مجموع الأسطر (كمية × سعر) من حالة الـ Repeater */
    public static function linesTotal(?array $items): float
    {
        return round(collect($items ?? [])->sum(
            fn ($i) => (is_numeric($i['quantity'] ?? null) ? (float) $i['quantity'] : 0)
                * (is_numeric($i['unit_cost'] ?? null) ? (float) $i['unit_cost'] : 0)
        ), 2);
    }
}
