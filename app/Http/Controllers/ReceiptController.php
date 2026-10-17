<?php

namespace App\Http\Controllers;

use App\Models\CustomerTransaction;
use App\Models\Sale;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Request $request, Sale $sale)
    {
        $sale->load(['items.product.unit', 'payments', 'customer', 'user']);

        $size = (string) $request->query('size', '');

        if (! in_array($size, ['58', '80', 'a4'], true)) {
            $size = (string) config('store.receipt_width', '80');
        }

        // رصيد العميل بعد هذه الفاتورة (إن كانت آجلة)
        $ledger = $sale->customer_id
            ? CustomerTransaction::where('sale_id', $sale->id)->where('type', 'debt')->first()
            : null;

        return view('receipts.show', [
            'sale' => $sale,
            'size' => $size,
            'ledger' => $ledger,
        ]);
    }
}
