@php
    $money = fn ($v) => '₪' . number_format((float) $v, 2);
    $cell = 'padding:.45rem .25rem;border-bottom:1px solid rgba(128,128,128,.25);';
@endphp

<div style="display:grid;gap:1.25rem;font-size:.92rem">
    <table style="width:100%;border-collapse:collapse">
        <tr><td style="{{ $cell }}">الرصيد الافتتاحي</td><td style="{{ $cell }}text-align:end">{{ $money($s['opening_cash']) }}</td></tr>
        <tr><td style="{{ $cell }}">(+) مبيعات نقدية</td><td style="{{ $cell }}text-align:end">{{ $money($s['cash_sales']) }}</td></tr>
        <tr><td style="{{ $cell }}">(+) دفعات عملاء نقدية</td><td style="{{ $cell }}text-align:end">{{ $money($s['customer_cash']) }}</td></tr>
        <tr><td style="{{ $cell }}">(+) إيرادات أخرى</td><td style="{{ $cell }}text-align:end">{{ $money($s['income']) }}</td></tr>
        <tr><td style="{{ $cell }}">(−) دفعات موردين نقدية</td><td style="{{ $cell }}text-align:end">{{ $money($s['supplier_cash']) }}</td></tr>
        <tr><td style="{{ $cell }}">(−) مرتجعات بيع نقدية</td><td style="{{ $cell }}text-align:end">{{ $money($s['sale_refunds']) }}</td></tr>
        <tr><td style="{{ $cell }}">(−) مصروفات</td><td style="{{ $cell }}text-align:end">{{ $money($s['expense']) }}</td></tr>
        <tr style="font-weight:800"><td style="{{ $cell }}">المتوقع في الدرج</td><td style="{{ $cell }}text-align:end">{{ $money($s['expected_cash']) }}</td></tr>
        @if ($shift->counted_cash !== null)
            <tr><td style="{{ $cell }}">المعدود فعلياً</td><td style="{{ $cell }}text-align:end">{{ $money($shift->counted_cash) }}</td></tr>
            <tr style="font-weight:800">
                <td style="{{ $cell }}">الفرق</td>
                <td style="{{ $cell }}text-align:end;color:{{ (float) $shift->difference == 0 ? '#16a34a' : '#dc2626' }}">
                    {{ $money($shift->difference) }}
                    @if ((float) $shift->difference < 0) (عجز) @elseif ((float) $shift->difference > 0) (زيادة) @endif
                </td>
            </tr>
        @endif
    </table>

    <div>
        <div style="font-weight:800;margin-bottom:.4rem">المبيعات حسب طريقة الدفع ({{ $s['sales_count'] }} فاتورة — {{ $money($s['sales_total']) }})</div>
        <table style="width:100%;border-collapse:collapse">
            <tr style="opacity:.7"><td style="{{ $cell }}">الطريقة</td><td style="{{ $cell }}text-align:end">عدد</td><td style="{{ $cell }}text-align:end">الإجمالي</td><td style="{{ $cell }}text-align:end">المدفوع</td><td style="{{ $cell }}text-align:end">دين</td></tr>
            @forelse ($s['by_method'] as $m)
                <tr>
                    <td style="{{ $cell }}">{{ $m['label'] }}</td>
                    <td style="{{ $cell }}text-align:end">{{ $m['count'] }}</td>
                    <td style="{{ $cell }}text-align:end">{{ $money($m['total']) }}</td>
                    <td style="{{ $cell }}text-align:end">{{ $money($m['paid']) }}</td>
                    <td style="{{ $cell }}text-align:end">{{ $money($m['due']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="{{ $cell }}opacity:.6">لا توجد مبيعات في هذه الوردية</td></tr>
            @endforelse
        </table>
    </div>
</div>
