<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** دفتر الديون اليومي: حركات ديون العملاء ودفعاتهم مع أصناف كل فاتورة آجلة */
class DebtLedgerService
{
    private function base(Carbon $from, Carbon $until, ?int $customerId, ?string $type)
    {
        return CustomerTransaction::query()
            ->whereBetween('created_at', [$from, $until])
            ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($type, fn ($q, $v) => $q->where('type', $v));
    }

    /** @return Collection<int, array<string, mixed>> أقدم حركة أولاً (حتى 1000 حركة) */
    public function entries(Carbon $from, Carbon $until, ?int $customerId = null, ?string $type = null): Collection
    {
        $tx = $this->base($from, $until, $customerId, $type)
            ->with('customer:id,name')
            ->orderBy('created_at')->orderBy('id')
            ->limit(1000)
            ->get();

        $items = SaleItem::whereIn('sale_id', $tx->pluck('sale_id')->filter()->unique())
            ->get()->groupBy('sale_id');

        return $tx->map(fn ($t) => [
            'date' => $t->created_at,
            'customer' => $t->customer?->name ?? '—',
            'type' => $t->type,
            'description' => (string) $t->description,
            'items' => $t->sale_id
                ? $items->get($t->sale_id, collect())
                    ->map(fn ($i) => $i->product_name . ' × ' . rtrim(rtrim(number_format((float) $i->quantity, 3, '.', ''), '0'), '.'))
                    ->implode('، ')
                : '',
            'amount' => (float) $t->amount,
            'balance_after' => (float) $t->balance_after,
            'method' => $t->payment_method,
        ]);
    }

    public function summary(Carbon $from, Carbon $until, ?int $customerId = null): array
    {
        $row = $this->base($from, $until, $customerId, null)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END), 0) as debts,
                         COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) as payments")
            ->first();

        $outstanding = Customer::query()
            ->when($customerId, fn ($q, $v) => $q->whereKey($v))
            ->where('balance', '>', 0);

        return [
            'debts' => (float) $row->debts,
            'payments' => (float) $row->payments,
            'net' => round((float) $row->debts - (float) $row->payments, 2),
            'outstanding' => (float) (clone $outstanding)->sum('balance'),
            'debtors' => (clone $outstanding)->count(),
        ];
    }

    /** ملخص لكل عميل: ديون الفترة ودفعاتها والدين الحالي */
    public function perCustomer(Carbon $from, Carbon $until, ?int $customerId = null): Collection
    {
        $rows = $this->base($from, $until, $customerId, null)
            ->selectRaw("customer_id,
                         SUM(CASE WHEN type = 'debt' THEN amount ELSE 0 END) as debts,
                         SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments")
            ->groupBy('customer_id')
            ->get();

        $customers = Customer::whereIn('id', $rows->pluck('customer_id'))->get(['id', 'name', 'balance'])->keyBy('id');

        return $rows->map(fn ($r) => [
            'customer' => $customers[$r->customer_id]->name ?? '—',
            'debts' => (float) $r->debts,
            'payments' => (float) $r->payments,
            'balance' => (float) ($customers[$r->customer_id]->balance ?? 0),
        ])->sortByDesc('balance')->values();
    }
}
