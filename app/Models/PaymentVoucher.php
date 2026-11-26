<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVoucher extends Model
{
    protected $fillable = [
        'number', 'supplier_id', 'amount', 'payment_method', 'payment_details', 'notes',
        'balance_after', 'shift_id', 'user_id', 'supplier_transaction_id',
        'status', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
