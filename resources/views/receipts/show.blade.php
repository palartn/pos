@php
    use App\Support\Money;
    use App\Support\PaymentMethod;
    use App\Support\Qty;

    $remaining = max(0, round((float) $sale->total - (float) $sale->paid, 2));
    $isA4 = $size === 'a4';
    $widthMm = $size === '58' ? 58 : 80;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isA4 ? 'فاتورة' : 'إيصال' }} {{ $sale->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            color: #000;
            background: #f3f4f6;
            font-size: {{ $isA4 ? '14px' : '12px' }};
            line-height: 1.5;
        }
        .toolbar {
            display: flex; gap: .5rem; justify-content: center; align-items: center;
            padding: .75rem; background: #fff; border-bottom: 1px solid #e5e7eb; flex-wrap: wrap;
        }
        .toolbar a, .toolbar button {
            padding: .4rem .9rem; border: 1px solid #d1d5db; border-radius: .4rem;
            background: #fff; color: #111; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .toolbar .active { background: #111; color: #fff; }

        .receipt {
            background: #fff; margin: 1rem auto;
            padding: {{ $isA4 ? '12mm' : '3mm' }};
            width: {{ $isA4 ? '210mm' : $widthMm . 'mm' }}; max-width: 100%;
            @if ($isA4) min-height: 297mm; @endif
        }

        .center { text-align: center; }
        .muted { color: #444; }
        .small { font-size: {{ $isA4 ? '12px' : '10.5px' }}; }

        /* ===== تصميم الإيصال الحراري ===== */
        .store-name { font-size: {{ $isA4 ? '24px' : '16px' }}; font-weight: 700; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .item { margin-bottom: 4px; }
        .item .name { font-weight: 700; }
        .total-row { font-weight: 700; font-size: 14px; }

        /* ===== تصميم فاتورة A4 ===== */
        .inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;
                    border-bottom: 3px solid #111; padding-bottom: 10px; margin-bottom: 14px; }
        .inv-title { text-align: left; }
        .inv-title .title { font-size: 22px; font-weight: 700; }
        .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
                     border: 1px solid #999; border-radius: 6px; padding: 8px 12px; margin-bottom: 14px; }
        .info-grid .label { color: #555; font-size: 12px; display: block; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th, table.items td { border: 1px solid #999; padding: 6px 8px; text-align: center; }
        table.items th { background: #e5e7eb; font-weight: 700; }
        table.items td.name { text-align: right; }
        table.items thead { display: table-header-group; }
        table.items tr { page-break-inside: avoid; }
        .totals { width: 52%; margin-inline-start: auto; border-collapse: collapse; margin-bottom: 14px; }
        .totals td { padding: 5px 8px; border-bottom: 1px solid #ccc; }
        .totals td:last-child { text-align: left; font-weight: 700; }
        .totals tr.grand td { border-top: 2px solid #111; border-bottom: 2px solid #111; font-size: 16px; }
        .totals tr.due td { color: #b91c1c; }
        table.pays { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.pays th, table.pays td { border: 1px solid #bbb; padding: 4px 8px; text-align: right; }
        table.pays th { background: #f3f4f6; }
        .sign { display: flex; justify-content: space-between; gap: 40px; margin-top: 36px; }
        .sign div { flex: 1; border-top: 1px solid #000; text-align: center; padding-top: 4px; font-size: 12px; }
        .footer { text-align: center; margin-top: 18px; }
        .notes { border: 1px dashed #999; padding: 6px 10px; margin-bottom: 14px; }

        @media print {
            @page { size: {{ $isA4 ? 'A4' : $widthMm . 'mm auto' }}; margin: {{ $isA4 ? '12mm' : '0' }}; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt { margin: 0; box-shadow: none; @if ($isA4) padding: 0; width: auto; min-height: 0; @endif }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">🖨 طباعة</button>
        <a href="{{ route('receipts.show', ['sale' => $sale, 'size' => 'a4']) }}" class="{{ $size === 'a4' ? 'active' : '' }}">A4</a>
        <a href="{{ route('receipts.show', ['sale' => $sale, 'size' => '80']) }}" class="{{ $size === '80' ? 'active' : '' }}">80 مم</a>
        <a href="{{ route('receipts.show', ['sale' => $sale, 'size' => '58']) }}" class="{{ $size === '58' ? 'active' : '' }}">58 مم</a>
    </div>

    <div class="receipt">
    @if ($isA4)
        {{-- ================= فاتورة A4 ================= --}}
        <div class="inv-head">
            <div>
                <div class="store-name">{{ config('store.name') }}</div>
                @if (config('store.address'))
                    <div class="muted">{{ config('store.address') }}</div>
                @endif
                @if (config('store.phone'))
                    <div class="muted">هاتف: {{ config('store.phone') }}</div>
                @endif
            </div>
            <div class="inv-title">
                <div class="title">فاتورة مبيعات</div>
                <div>رقم الفاتورة: <strong>{{ $sale->invoice_number }}</strong></div>
                <div>التاريخ: {{ $sale->created_at->format('Y-m-d H:i') }}</div>
            </div>
        </div>

        <div class="info-grid">
            <div><span class="label">العميل</span><strong>{{ $sale->customer?->name ?? 'عميل نقدي' }}</strong>
                @if ($sale->customer?->phone)<span class="small muted"> — {{ $sale->customer->phone }}</span>@endif
            </div>
            <div><span class="label">الكاشير</span><strong>{{ $sale->user?->name }}</strong></div>
            <div><span class="label">الحالة</span><strong>{{ $remaining > 0 ? 'آجل (متبقٍ على الحساب)' : 'مدفوعة' }}</strong></div>
        </div>

        @if ($sale->notes)
            <div class="notes"><strong>ملاحظات:</strong> {!! nl2br(e($sale->notes)) !!}</div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th style="width:6%">#</th>
                    <th>الصنف</th>
                    <th style="width:12%">الوحدة</th>
                    <th style="width:12%">الكمية</th>
                    <th style="width:16%">سعر الوحدة</th>
                    <th style="width:16%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="name">{{ $item->product?->name ?? 'منتج محذوف' }}</td>
                        <td>{{ $item->product?->unit?->name }}</td>
                        <td>{{ Qty::format($item->quantity) }}</td>
                        <td>{{ Money::format($item->unit_price) }}</td>
                        <td>{{ Money::format($item->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>المجموع</td><td>{{ Money::format($sale->subtotal) }}</td></tr>
            @if ((float) $sale->discount > 0)
                <tr><td>الخصم</td><td>- {{ Money::format($sale->discount) }}</td></tr>
            @endif
            <tr class="grand"><td>الإجمالي</td><td>{{ Money::format($sale->total) }}</td></tr>
            <tr><td>المدفوع</td><td>{{ Money::format($sale->paid) }}</td></tr>
            @if ($remaining > 0)
                <tr class="due"><td>المتبقي على الحساب</td><td>{{ Money::format($remaining) }}</td></tr>
            @endif
            @if ($ledger)
                <tr><td>رصيد العميل المستحق بعد الفاتورة</td><td>{{ Money::format($ledger->balance_after) }}</td></tr>
            @endif
        </table>

        @if ($sale->payments->count())
            <table class="pays">
                <thead>
                    <tr><th>طريقة الدفع</th><th>المبلغ</th><th>رقم العملية / التحديد</th></tr>
                </thead>
                <tbody>
                    @foreach ($sale->payments as $payment)
                        <tr>
                            <td>{{ PaymentMethod::label($payment->method) }}</td>
                            <td>{{ Money::format($payment->amount) }}</td>
                            <td>{{ $payment->reference ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sign">
            <div>توقيع المستلم</div>
            <div>ختم / توقيع البائع</div>
        </div>

        <div class="footer muted">{{ config('store.receipt_footer') }}</div>
    @else
        {{-- ================= إيصال حراري ================= --}}
        <div class="center">
            <div class="store-name">{{ config('store.name') }}</div>
            @if (config('store.address'))
                <div class="muted small">{{ config('store.address') }}</div>
            @endif
            @if (config('store.phone'))
                <div class="muted small">{{ config('store.phone') }}</div>
            @endif
        </div>

        <hr>

        <div class="row"><span>رقم الفاتورة</span><strong>{{ $sale->invoice_number }}</strong></div>
        <div class="row"><span>التاريخ</span><span>{{ $sale->created_at->format('Y-m-d H:i') }}</span></div>
        <div class="row"><span>الكاشير</span><span>{{ $sale->user?->name }}</span></div>
        <div class="row"><span>العميل</span><span>{{ $sale->customer?->name ?? 'عميل نقدي' }}</span></div>

        <hr>

        @foreach ($sale->items as $item)
            <div class="item">
                <div class="name">{{ $item->product?->name ?? 'منتج محذوف' }}</div>
                <div class="row">
                    <span>
                        {{ Qty::format($item->quantity) }}
                        @if ($item->product?->unit) {{ $item->product->unit->name }} @endif
                        × {{ Money::format($item->unit_price) }}
                    </span>
                    <span>{{ Money::format($item->total) }}</span>
                </div>
            </div>
        @endforeach

        <hr>

        <div class="row"><span>المجموع</span><span>{{ Money::format($sale->subtotal) }}</span></div>
        @if ((float) $sale->discount > 0)
            <div class="row"><span>الخصم</span><span>- {{ Money::format($sale->discount) }}</span></div>
        @endif
        <div class="row total-row"><span>الإجمالي</span><span>{{ Money::format($sale->total) }}</span></div>

        <hr>

        @foreach ($sale->payments as $payment)
            <div class="row">
                <span>{{ PaymentMethod::label($payment->method) }}</span>
                <span>{{ Money::format($payment->amount) }}</span>
            </div>
            @if ($payment->reference)
                <div class="muted small">{{ $payment->reference }}</div>
            @endif
        @endforeach

        @if ($remaining > 0)
            <div class="row total-row"><span>المتبقي على الحساب</span><span>{{ Money::format($remaining) }}</span></div>
        @endif

        @if ($ledger)
            <div class="row small"><span>رصيد العميل المستحق</span><span>{{ Money::format($ledger->balance_after) }}</span></div>
        @endif

        @if ($sale->notes)
            <hr>
            <div class="small"><strong>ملاحظات:</strong> {!! nl2br(e($sale->notes)) !!}</div>
        @endif

        <hr>

        <div class="center small">{{ config('store.receipt_footer') }}</div>
    @endif
    </div>

    @if (request()->boolean('print'))
        <script>
            window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
        </script>
    @endif
</body>
</html>
