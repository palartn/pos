<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'number', 'type', 'customer_id', 'amount', 'payment_method', 'payment_details', 'notes',
        'balance_after', 'shift_id', 'user_id', 'customer_transaction_id',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }
}
