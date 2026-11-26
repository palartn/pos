@php
    $width = \App\Models\Setting::get('receipt_width', '80') === '58' ? 58 : 80;
    $money = fn ($v) => '₪' . number_format((float) $v, 2);
    $method = \App\Enums\PaymentMethod::tryFrom($voucher->payment_method)?->label() ?? $voucher->payment_method;
    $before = (float) $voucher->balance_after + (float) $voucher->amount;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $voucher->number }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: {{ $width - 4 }}mm; font-family: Tahoma, Arial, sans-serif; font-size: {{ $width === 58 ? 11 : 12.5 }}px; color: #000; position: relative; }
        h1 { font-size: 1.3em; text-align: center; margin: 4px 0; }
        h2 { font-size: 1.15em; text-align: center; margin: 6px 0; border: 1px solid #000; padding: 3px; }
        .c { text-align: center; }
        .muted { font-size: .9em; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; padding: 2px 0; }
        .amount { font-size: 1.5em; font-weight: bold; text-align: center; border: 2px solid #000; padding: 6px; margin: 6px 0; }
        .sign { display: flex; justify-content: space-between; margin-top: 22px; }
        .sign div { width: 45%; text-align: center; border-top: 1px solid #000; padding-top: 3px; }
        .void { text-align: center; font-size: 1.6em; font-weight: bold; color: #b00; border: 3px solid #b00; padding: 4px; margin: 6px 0; transform: rotate(-4deg); }
        .no-print { text-align: center; margin: 8px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ \App\Models\Setting::get('store_name', 'السوبر ماركت') }}</h1>
    <div class="c muted">
        @if (\App\Models\Setting::get('store_address')) {{ \App\Models\Setting::get('store_address') }}<br>@endif
        @if (\App\Models\Setting::get('store_phone')) هاتف: {{ \App\Models\Setting::get('store_phone') }} @endif
    </div>

    <h2>سند قبض</h2>

    @if ($voucher->isCancelled())
        <div class="void">ملغى</div>
    @endif

    <div class="row"><span>رقم السند</span><strong>{{ $voucher->number }}</strong></div>
    <div class="row"><span>التاريخ</span><span>{{ $voucher->created_at->format('Y-m-d H:i') }}</span></div>
    <hr>
    <div class="row"><span>استلمنا من السيد/ة</span><strong>{{ $voucher->customer?->name }}</strong></div>

    <div class="amount">{{ $money($voucher->amount) }}</div>

    <div class="row"><span>طريقة الدفع</span><span>{{ $method }}</span></div>
    @if ($voucher->payment_details)
        <div class="row muted"><span>تفاصيل</span><span>{{ $voucher->payment_details }}</span></div>
    @endif
    <div class="row"><span>الدين قبل السند</span><span>{{ $money($before) }}</span></div>
    <div class="row"><span>الدين بعد السند</span><strong>{{ $money($voucher->balance_after) }}</strong></div>
    @if ($voucher->notes)
        <div class="row muted"><span>ملاحظات</span><span>{{ $voucher->notes }}</span></div>
    @endif
    @if ($voucher->isCancelled())
        <hr>
        <div class="muted">أُلغي بتاريخ {{ $voucher->cancelled_at?->format('Y-m-d H:i') }}
            بواسطة {{ $voucher->canceller?->name }} — السبب: {{ $voucher->cancel_reason }}</div>
    @endif

    <div class="sign">
        <div>المستلِم: {{ $voucher->user?->name }}</div>
        <div>المسلِّم</div>
    </div>

    <div class="no-print"><button onclick="window.print()">طباعة</button></div>

    @if ($autoprint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
