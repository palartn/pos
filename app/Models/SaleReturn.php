<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    protected $fillable = [
        'return_number', 'sale_id', 'customer_id', 'warehouse_id', 'shift_id', 'user_id',
        'total', 'debt_reduced', 'cash_refund', 'refund_method', 'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'debt_reduced' => 'decimal:2',
        'cash_refund' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
