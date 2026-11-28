@php
    $store = \App\Models\Setting::get('store_name', '');
    $r = $report;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; padding: 16px; background: #fff; color: #000; font-family: Tahoma, Arial, sans-serif; font-size: 12px; }
        .head { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { margin: 2px 0 4px; font-size: 20px; }
        .head div { color: #333; }
        .note { border: 1px dashed #999; padding: 6px 10px; margin-bottom: 10px; color: #444; font-size: 11px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; }
        .stat { border: 1px solid #bbb; border-inline-start: 4px solid #999; padding: 7px 9px; break-inside: avoid; }
        .stat span { display: block; font-size: 11px; color: #555; }
        .stat strong { font-size: 15px; }
        .stat.good { border-inline-start-color: #16a34a; } .stat.good strong { color: #16a34a; }
        .stat.bad { border-inline-start-color: #dc2626; } .stat.bad strong { color: #dc2626; }
        .stat.warn { border-inline-start-color: #d97706; } .stat.warn strong { color: #d97706; }
        .card { border: 1px solid #bbb; padding: 8px 10px; margin-bottom: 12px; }
        .card h3 { margin: 0 0 8px; font-size: 14px; }
        .chart { display: flex; align-items: flex-end; gap: 4px; height: 130px; }
        .bar { flex: 1 1 0; max-width: 46px; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; gap: 3px; }
        .bar i { display: block; width: 100%; background: #059669; min-height: 2px; }
        .bar small { font-size: 9px; color: #555; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { text-align: start; color: #555; padding: 5px 6px; border-bottom: 2px solid #999; white-space: nowrap; }
        td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
        th:not(:first-child), td:not(:first-child) { text-align: end; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        tfoot td { font-weight: bold; border-top: 2px solid #999; border-bottom: 0; }
        .empty { text-align: center !important; color: #777; padding: 14px !important; }
        .toolbar { text-align: center; margin-bottom: 12px; }
        .toolbar button { padding: 8px 20px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ طباعة</button></div>

    <div class="head">
        @if ($store)<div>{{ $store }}</div>@endif
        <h1>{{ $title }}</h1>
        <div>الفترة: {{ $filters['from'] ?? '—' }} إلى {{ $filters['until'] ?? '—' }} — طُبع في {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    @if (! empty($r['note']))
        <div class="note">{{ $r['note'] }}</div>
    @endif

    @if (! empty($r['stats']))
        <div class="stats">
            @foreach ($r['stats'] as $s)
                <div class="stat {{ $s['tone'] ?? '' }}"><span>{{ $s['label'] }}</span><strong>{{ $s['value'] }}</strong></div>
            @endforeach
        </div>
    @endif

    @if (! empty($r['chart']) && count($r['chart']['values']))
        @php $max = max(1, max($r['chart']['values'])); @endphp
        <div class="card">
            <h3>{{ $r['chart']['title'] }}</h3>
            <div class="chart">
                @foreach ($r['chart']['values'] as $i => $v)
                    <div class="bar"><i style="height: {{ max(2, round($v / $max * 100)) }}%"></i><small>{{ $r['chart']['labels'][$i] ?? '' }}</small></div>
                @endforeach
            </div>
        </div>
    @endif

    @foreach ($r['tables'] ?? [] as $t)
        <div class="card">
            <h3>{{ $t['title'] }}</h3>
            <table>
                <thead><tr>@foreach ($t['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($t['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($t['headers']) }}" class="empty">لا توجد بيانات في هذه الفترة</td></tr>
                    @endforelse
                </tbody>
                @if (! empty($t['footer']))
                    <tfoot><tr>@foreach ($t['footer'] as $cell)<td>{{ $cell }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    @endforeach

    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
