<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierTransaction extends Model
{
    protected $fillable = [
        'supplier_id', 'type', 'amount', 'balance_after',
        'payment_method', 'payment_details', 'description', 'user_id', 'shift_id',
    ];

    protected $casts = [
        'type' => LedgerType::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
