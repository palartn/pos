<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTake extends Model
{
    protected $fillable = [
        'number', 'warehouse_id', 'user_id', 'status', 'notes',
        'approved_at', 'approved_by', 'difference_value',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'difference_value' => 'decimal:2',
    ];

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
