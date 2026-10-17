<x-filament-panels::page>
    <div dir="rtl" style="display:grid; gap:1rem;">

        @if (! $this->currentSession)
            {{-- ===== لا توجد جلسة مفتوحة: نموذج الفتح ===== --}}
            <x-filament::section
                heading="فتح جلسة جديدة"
                description="أدخل النقد الموجود في الدرج الآن قبل البدء بالبيع"
            >
                <div style="max-width:420px; display:grid; gap:1rem;">
                    <div>
                        <label style="display:block; font-size:.9rem; margin-bottom:.35rem;">الرصيد الافتتاحي</label>
                        <x-filament::input.wrapper :valid="! $errors->has('openingBalance')">
                            <x-filament::input type="number" step="0.01" min="0" wire:model="openingBalance" />
                        </x-filament::input.wrapper>
                        @error('openingBalance')
                            <p style="color:#dc2626; font-size:.85rem; margin-top:.25rem;">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-filament::button wire:click="openSession" wire:loading.attr="disabled" color="success">
                        فتح الجلسة
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            {{-- ===== جلسة مفتوحة: ملخص + إغلاق ===== --}}
            @php
                $session = $this->currentSession;
                $st = $this->stats;
                $card = 'border:1px solid rgba(128,128,128,.3); border-radius:.5rem; padding:.75rem 1rem;';
            @endphp

            <x-filament::section
                heading="الجلسة الحالية"
                description="فُتحت: {{ $session->opened_at->format('Y-m-d H:i') }}"
            >
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:.75rem;">
                    <div style="{{ $card }}">
                        <div style="font-size:.8rem; opacity:.7;">الرصيد الافتتاحي</div>
                        <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($session->opening_balance) }}</div>
                    </div>
                    <div style="{{ $card }}">
                        <div style="font-size:.8rem; opacity:.7;">عدد الفواتير</div>
                        <div style="font-size:1.25rem; font-weight:700;">{{ $st['count'] }}</div>
                    </div>
                    <div style="{{ $card }}">
                        <div style="font-size:.8rem; opacity:.7;">إجمالي المبيعات</div>
                        <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($st['total']) }}</div>
                    </div>
                    <div style="{{ $card }}">
                        <div style="font-size:.8rem; opacity:.7;">مبيعات نقدية</div>
                        <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($st['cash']) }}</div>
                    </div>
                    @foreach ($st['others'] as $method => $amount)
                        @if ($amount > 0)
                            <div style="{{ $card }}">
                                <div style="font-size:.8rem; opacity:.7;">{{ \App\Support\PaymentMethod::label($method) }}</div>
                                <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($amount) }}</div>
                            </div>
                        @endif
                    @endforeach

                    @if ($st['debt_cash'] > 0)
                        <div style="{{ $card }}">
                            <div style="font-size:.8rem; opacity:.7;">تحصيل ديون (نقدي)</div>
                            <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($st['debt_cash']) }}</div>
                        </div>
                    @endif

                    @if ($st['debt_other'] > 0)
                        <div style="{{ $card }}">
                            <div style="font-size:.8rem; opacity:.7;">تحصيل ديون (غير نقدي)</div>
                            <div style="font-size:1.25rem; font-weight:700;">{{ \App\Support\Money::format($st['debt_other']) }}</div>
                        </div>
                    @endif
                </div>

                <div style="{{ $card }} margin-top:.75rem; border-color:#16a34a;">
                    <div style="font-size:.8rem; opacity:.7;">النقد المتوقع في الدرج (الافتتاحي + المبيعات النقدية + تحصيل الديون النقدي)</div>
                    <div style="font-size:1.5rem; font-weight:700;">{{ \App\Support\Money::format($st['expected']) }}</div>
                </div>
            </x-filament::section>

            <x-filament::section
                heading="إغلاق الجلسة"
                description="عُدّ النقد الموجود فعلياً في الدرج وأدخله، وسيحسب النظام الفرق"
            >
                <div style="max-width:420px; display:grid; gap:1rem;">
                    <div>
                        <label style="display:block; font-size:.9rem; margin-bottom:.35rem;">النقد الفعلي في الدرج</label>
                        <x-filament::input.wrapper :valid="! $errors->has('countedCash')">
                            <x-filament::input type="number" step="0.01" min="0" wire:model="countedCash" />
                        </x-filament::input.wrapper>
                        @error('countedCash')
                            <p style="color:#dc2626; font-size:.85rem; margin-top:.25rem;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label style="display:block; font-size:.9rem; margin-bottom:.35rem;">ملاحظات (اختياري)</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="notes" />
                        </x-filament::input.wrapper>
                    </div>

                    <x-filament::button
                        wire:click="closeSession"
                        wire:loading.attr="disabled"
                        wire:confirm="هل أنت متأكد من إغلاق الجلسة؟ لا يمكن التراجع بعد الإغلاق."
                        color="danger"
                    >
                        إغلاق الجلسة
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- ===== سجل آخر الجلسات ===== --}}
        @if ($this->history->count())
            <x-filament::section heading="آخر الجلسات المغلقة">
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
                        <thead>
                            <tr style="text-align:right; border-bottom:1px solid rgba(128,128,128,.3);">
                                <th style="padding:.5rem;">الفتح</th>
                                <th style="padding:.5rem;">الإغلاق</th>
                                <th style="padding:.5rem;">الافتتاحي</th>
                                <th style="padding:.5rem;">المتوقع</th>
                                <th style="padding:.5rem;">الفعلي</th>
                                <th style="padding:.5rem;">الفرق</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->history as $h)
                                <tr style="border-bottom:1px solid rgba(128,128,128,.15);">
                                    <td style="padding:.5rem;">{{ $h->opened_at->format('Y-m-d H:i') }}</td>
                                    <td style="padding:.5rem;">{{ $h->closed_at?->format('Y-m-d H:i') }}</td>
                                    <td style="padding:.5rem;">{{ \App\Support\Money::format($h->opening_balance) }}</td>
                                    <td style="padding:.5rem;">{{ \App\Support\Money::format($h->expected_balance ?? 0) }}</td>
                                    <td style="padding:.5rem;">{{ \App\Support\Money::format($h->closing_balance ?? 0) }}</td>
                                    <td style="padding:.5rem; font-weight:700; color:{{ $h->difference < 0 ? '#dc2626' : ($h->difference > 0 ? '#d97706' : '#16a34a') }};">
                                        {{ \App\Support\Money::format($h->difference ?? 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
