<x-filament-panels::page>
    @php($r = $this->reportData)

    <div class="rp">
        <div class="rp-card rp-filters">
            {{ $this->form }}
            <div class="rp-actions">
                <button type="button" class="rp-btn" onclick="window.print()">🖨️ طباعة</button>
            </div>
        </div>

        @if (! empty($r['note']))
            <div class="rp-note">{{ $r['note'] }}</div>
        @endif

        @if (! empty($r['stats']))
            <div class="rp-stats">
                @foreach ($r['stats'] as $s)
                    <div class="rp-stat rp-{{ $s['tone'] ?? 'plain' }}">
                        <span>{{ $s['label'] }}</span>
                        <strong>{{ $s['value'] }}</strong>
                    </div>
                @endforeach
            </div>
        @endif

        @if (! empty($r['chart']) && count($r['chart']['values']))
            @php($max = max(1, max($r['chart']['values'])))
            <div class="rp-card">
                <h3>{{ $r['chart']['title'] }}</h3>
                <div class="rp-chart">
                    @foreach ($r['chart']['values'] as $i => $v)
                        <div class="rp-bar" title="{{ $r['chart']['titles'][$i] ?? '' }}">
                            <i style="height: {{ max(2, round($v / $max * 100)) }}%"></i>
                            <small>{{ $r['chart']['labels'][$i] ?? '' }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @foreach ($r['tables'] ?? [] as $t)
            <div class="rp-card">
                <h3>{{ $t['title'] }}</h3>
                <div class="rp-scroll">
                    <table class="rp-table">
                        <thead>
                            <tr>@foreach ($t['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @forelse ($t['rows'] as $row)
                                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($t['headers']) }}" class="rp-empty">لا توجد بيانات في هذه الفترة</td></tr>
                            @endforelse
                        </tbody>
                        @if (! empty($t['footer']))
                            <tfoot>
                                <tr>@foreach ($t['footer'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
