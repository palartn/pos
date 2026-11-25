@php
    $width = \App\Models\Setting::get('receipt_width', '80') === '58' ? 58 : 80;
    $name = \App\Models\Setting::get('store_name', 'السوبر ماركت');
    $rate = (float) \App\Models\Setting::get('tax_rate', 0);
    $taxAmount = $rate > 0 ? round((float) $sale->total * $rate / (100 + $rate), 2) : 0;
    $money = fn ($v) => '₪' . number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_number }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        body { margin: 0 auto; width: {{ $width - 4 }}mm; font-family: Tahoma, Arial, sans-serif; font-size: {{ $width === 58 ? 11 : 12.5 }}px; color: #000; }
        h1 { font-size: 1.35em; text-align: center; margin: 4px 0; }
        .c { text-align: center; }
        .muted { font-size: .9em; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: start; font-size: .9em; border-bottom: 1px solid #000; padding: 2px 0; }
        td { padding: 3px 0; vertical-align: top; }
        .e { text-align: end; white-space: nowrap; }
        .row { display: flex; justify-content: space-between; padding: 1px 0; }
        .total { font-size: 1.3em; font-weight: bold; }
        .no-print { text-align: center; margin: 8px 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <h1>{{ $name }}</h1>
    <div class="c muted">
        @if (\App\Models\Setting::get('store_address')) {{ \App\Models\Setting::get('store_address') }}<br>@endif
        @if (\App\Models\Setting::get('store_phone')) هاتف: {{ \App\Models\Setting::get('store_phone') }}<br>@endif
        @if (\App\Models\Setting::get('tax_number')) الرقم الضريبي: {{ \App\Models\Setting::get('tax_number') }} @endif
    </div>
    <hr>
    <div class="row"><span>فاتورة رقم</span><strong>{{ $sale->invoice_number }}</strong></div>
    <div class="row"><span>التاريخ</span><span>{{ $sale->created_at->format('Y-m-d H:i') }}</span></div>
    <div class="row"><span>الكاشير</span><span>{{ $sale->user?->name }}</span></div>
    <div class="row"><span>العميل</span><span>{{ $sale->customer?->name ?? 'نقدي' }}</span></div>
    <hr>
    <table>
        <thead><tr><th>الصنف</th><th class="e">الكمية</th><th class="e">السعر</th><th class="e">الإجمالي</th></tr></thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="e">{{ $qty($item->quantity) }}</td>
                    <td class="e">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="e">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <hr>
    <div class="row"><span>المجموع</span><span>{{ $money($sale->subtotal) }}</span></div>
    @if ((float) $sale->discount > 0)
        <div class="row"><span>الخصم</span><span>- {{ $money($sale->discount) }}</span></div>
    @endif
    <div class="row total"><span>الإجمالي</span><span>{{ $money($sale->total) }}</span></div>
    @if ($taxAmount > 0)
        <div class="row muted"><span>منها ضريبة القيمة المضافة ({{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%)</span><span>{{ $money($taxAmount) }}</span></div>
    @endif
    <hr>
    <div class="row"><span>طريقة الدفع</span><span>{{ \App\Enums\PaymentMethod::tryFrom($sale->payment_method)?->label() ?? $sale->payment_method }}</span></div>
    @if ($sale->payment_details)
        <div class="row muted"><span>تفاصيل</span><span>{{ $sale->payment_details }}</span></div>
    @endif
    <div class="row"><span>المدفوع</span><span>{{ $money($sale->paid) }}</span></div>
    @if ((float) $sale->change_amount > 0)
        <div class="row"><span>الباقي للعميل</span><span>{{ $money($sale->change_amount) }}</span></div>
    @endif
    @if ((float) $sale->due_amount > 0)
        <div class="row"><strong>المتبقي (دين)</strong><strong>{{ $money($sale->due_amount) }}</strong></div>
    @endif
    <hr>
    <div class="c">{{ \App\Models\Setting::get('receipt_footer', 'شكراً لزيارتكم') }}</div>

    <div class="no-print"><button onclick="window.print()">طباعة</button></div>

    @if ($autoprint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
