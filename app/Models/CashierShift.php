<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'opening_cash',
        'expected_cash', 'counted_cash', 'difference', 'status', 'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public static function openFor(?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return static::where('user_id', $userId)->where('status', 'open')->latest('id')->first();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'shift_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    /** ملخص الوردية والنقد المتوقع في الدرج */
    public function summary(): array
    {
        $id = $this->id;

        $byMethod = Sale::where('shift_id', $id)
            ->selectRaw('payment_method, COUNT(*) as c, SUM(total) as t, SUM(paid) as p, SUM(due_amount) as d')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($r) => [
                'label' => PaymentMethod::tryFrom($r->payment_method)?->label() ?? $r->payment_method,
                'count' => (int) $r->c,
                'total' => (float) $r->t,
                'paid' => (float) $r->p,
                'due' => (float) $r->d,
            ])->all();

        $cashSales = (float) Sale::where('shift_id', $id)->where('payment_method', 'cash')->sum('paid');

        $customerCash = (float) CustomerTransaction::where('shift_id', $id)
            ->where('type', 'payment')->where('payment_method', 'cash')->sum('amount');

        $supplierCash = (float) SupplierTransaction::where('shift_id', $id)
            ->where('type', 'payment')->where('payment_method', 'cash')->sum('amount');

        $income = (float) CashMovement::where('shift_id', $id)->where('type', 'income')->sum('amount');
        $expense = (float) CashMovement::where('shift_id', $id)->where('type', 'expense')->sum('amount');

        $opening = (float) $this->opening_cash;

        return [
            'opening_cash' => $opening,
            'sales_count' => array_sum(array_column($byMethod, 'count')),
            'sales_total' => array_sum(array_column($byMethod, 'total')),
            'by_method' => $byMethod,
            'cash_sales' => $cashSales,
            'customer_cash' => $customerCash,
            'supplier_cash' => $supplierCash,
            'income' => $income,
            'expense' => $expense,
            'expected_cash' => round($opening + $cashSales + $customerCash + $income - $supplierCash - $expense, 2),
        ];
    }
}
