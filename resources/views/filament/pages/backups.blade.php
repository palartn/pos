<x-filament-panels::page>
    @php
        $list = $this->backups;
        $last = $list[0] ?? null;
        $stale = ! $last || $last['created_at']->lt(now()->subHours(48));
    @endphp

    <div class="rp">
        <div class="rp-note" @if ($stale) style="border-color:#dc2626;color:#dc2626" @endif>
            @if ($last)
                آخر نسخة: {{ $last['created_at']->format('Y-m-d H:i') }} ({{ $last['created_at']->diffForHumans() }})
                @if ($stale) — مضى أكثر من يومين، تحقق من المهمة المجدولة. @endif
            @else
                لا توجد أي نسخة احتياطية بعد. اضغط «إنشاء نسخة الآن».
            @endif
            <br>
            المجلد: <span dir="ltr">{{ $this->directory }}</span>
            @if (config('backup.copy_path')) — نسخة إضافية إلى: <span dir="ltr">{{ config('backup.copy_path') }}</span> @endif
            — تُحذف النسخ الأقدم من {{ config('backup.keep_days') }} يوماً تلقائياً.
        </div>

        <div class="rp-card">
            <h3>النسخ المتوفرة ({{ count($list) }})</h3>
            <div class="rp-scroll">
                <table class="rp-table">
                    <thead><tr><th>الملف</th><th>التاريخ</th><th>الحجم</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($list as $b)
                            <tr wire:key="bk-{{ $b['name'] }}">
                                <td dir="ltr" style="text-align:start">{{ $b['name'] }}</td>
                                <td>{{ $b['created_at']->format('Y-m-d H:i') }}</td>
                                <td>{{ number_format($b['size'] / 1024, 1) }} KB</td>
                                <td>
                                    <button type="button" class="rp-btn" wire:click="download('{{ $b['name'] }}')">تنزيل</button>
                                    <button type="button" class="rp-btn" style="color:#dc2626"
                                            wire:click="remove('{{ $b['name'] }}')"
                                            wire:confirm="حذف هذه النسخة نهائياً؟">حذف</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="rp-empty">لا توجد نسخ</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rp-note">
            الاسترجاع يتم من سطر الأوامر فقط (حفاظاً على البيانات):
            <br><span dir="ltr">php artisan db:restore backup-YYYYMMDD-HHMMSS.sql.gz</span>
            <br>تُنشأ نسخة أمان تلقائياً قبل الاسترجاع. احفظ نسخة خارج الجهاز بانتظام (فلاشة أو قرص خارجي).
        </div>
    </div>
</x-filament-panels::page>
