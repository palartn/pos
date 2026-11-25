<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    protected $fillable = [
        'stock_take_id', 'product_id', 'product_name', 'barcode',
        'system_qty', 'counted_qty', 'cost_price',
    ];

    protected $casts = [
        'system_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'cost_price' => 'decimal:2',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** الفرق = الفعلي - النظام (null إن لم يُجرد الصنف) */
    public function difference(): ?float
    {
        return $this->counted_qty === null
            ? null
            : round((float) $this->counted_qty - (float) $this->system_qty, 3);
    }
}
