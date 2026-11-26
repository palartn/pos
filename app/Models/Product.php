<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'barcode', 'unit', 'sold_by_weight',
        'cost_price', 'retail_price', 'wholesale_price',
        'stock', 'min_stock', 'is_active',
    ];

    protected $casts = [
        'sold_by_weight' => 'boolean',
        'is_active' => 'boolean',
        'cost_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /** سعر البيع حسب مستوى العميل؛ إن لم يُحدَّد سعر جملة يُستخدم سعر التجزئة */
    public function priceFor(string $level): float
    {
        if ($level === 'wholesale' && (float) $this->wholesale_price > 0) {
            return (float) $this->wholesale_price;
        }

        return (float) $this->retail_price;
    }

    /** إجمالي الكمية في كل المخازن (عمود stock يُحدَّث تلقائياً من StockService) */
    public function isLowStock(): bool
    {
        return (float) $this->stock <= (float) $this->min_stock;
    }

    /** كمية المخزن المحمَّل في علاقة stocks (يُستخدم في شاشة POS) */
    public function warehouseQty(): float
    {
        return $this->relationLoaded('stocks')
            ? (float) ($this->stocks->first()->quantity ?? 0)
            : (float) $this->stock;
    }
}
