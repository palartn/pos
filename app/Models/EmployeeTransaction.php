<?php

namespace App\Models;

use App\Enums\EmployeeTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTransaction extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'amount', 'occurred_on', 'description',
        'payroll_id', 'cash_movement_id', 'user_id',
    ];

    protected $casts = [
        'type' => EmployeeTransactionType::class,
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
