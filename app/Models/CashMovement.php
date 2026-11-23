<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $fillable = ['shift_id', 'user_id', 'type', 'category', 'amount', 'description', 'occurred_on'];

    protected $casts = [
        'type' => CashMovementType::class,
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
