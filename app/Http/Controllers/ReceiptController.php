<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function show(Request $request, Sale $sale)
    {
        $user = $request->user();

        abort_unless($user && ($user->can('sales.view') || $user->can('pos.access')), 403);
        abort_unless($sale->user_id === $user->id || $user->can('reports.view'), 403);

        $sale->load(['items', 'customer', 'user']);

        return view('receipt', [
            'sale' => $sale,
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }
}
