<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function show(Request $request, Voucher $voucher)
    {
        $user = $request->user();

        abort_unless($user, 403);

        $allowed = $user->can('vouchers.view')
            || ($user->can('vouchers.create') && $voucher->user_id === $user->id);

        abort_unless($allowed, 403);

        $voucher->load(['customer', 'user', 'canceller']);

        return view('voucher', [
            'voucher' => $voucher,
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }
}
