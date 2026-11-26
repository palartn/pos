<?php

namespace App\Http\Controllers;

use App\Models\PaymentVoucher;
use Illuminate\Http\Request;

class PaymentVoucherController extends Controller
{
    public function show(Request $request, PaymentVoucher $paymentVoucher)
    {
        $user = $request->user();

        abort_unless($user, 403);

        $allowed = $user->can('payvouchers.view')
            || ($user->can('payvouchers.create') && $paymentVoucher->user_id === $user->id);

        abort_unless($allowed, 403);

        $paymentVoucher->load(['supplier', 'user', 'canceller']);

        return view('payment-voucher', [
            'voucher' => $paymentVoucher,
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }
}
