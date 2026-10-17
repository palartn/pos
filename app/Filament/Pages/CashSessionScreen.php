<?php

namespace App\Filament\Pages;

use App\Models\CashSession;
use App\Models\CustomerTransaction;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashSessionScreen extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static string $view = 'filament.pages.cash-session-screen';
    protected static ?string $title = 'جلسة الكاشير';
    protected static ?string $navigationLabel = 'جلسة الكاشير';
    protected static ?string $navigationGroup = 'المبيعات';
    protected static ?int $navigationSort = 1;

    public $openingBalance = 0;
    public $countedCash = null;
    public $notes = '';

    /**
     * الجلسة المفتوحة للمستخدم الحالي (إن وجدت).
     */
    public function getCurrentSessionProperty(): ?CashSession
    {
        return CashSession::where('user_id', Auth::id())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * إحصائيات الجلسة المفتوحة.
     */
    public function getStatsProperty(): ?array
    {
        $session = $this->currentSession;

        return $session ? $this->sessionStats($session) : null;
    }

    /**
     * آخر 10 جلسات مغلقة للمستخدم الحالي.
     */
    public function getHistoryProperty()
    {
        return CashSession::where('user_id', Auth::id())
            ->where('status', 'closed')
            ->latest('closed_at')
            ->limit(10)
            ->get();
    }

    /**
     * حساب ملخص الجلسة. النقد المتوقع = الرصيد الافتتاحي + المدفوعات النقدية فقط.
     */
    protected function sessionStats(CashSession $session): array
    {
        $byMethod = Payment::query()
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $session->id))
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $cash = (float) ($byMethod['cash'] ?? 0);

        // دفعات الديون المستلمة خلال هذه الجلسة (النقدي منها يدخل الدرج)
        $debtPayments = CustomerTransaction::query()
            ->where('cash_session_id', $session->id)
            ->where('type', 'payment')
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $debtCash = (float) ($debtPayments['cash'] ?? 0);
        $debtOther = (float) $debtPayments->except('cash')->sum();

        return [
            'count' => Sale::where('cash_session_id', $session->id)->count(),
            'total' => (float) Sale::where('cash_session_id', $session->id)->sum('total'),
            'cash' => $cash,
            'others' => $byMethod->except('cash')->map(fn ($v) => (float) $v)->all(),
            'debt_cash' => $debtCash,
            'debt_other' => $debtOther,
            'expected' => (float) $session->opening_balance + $cash + $debtCash,
        ];
    }

    public function openSession(): void
    {
        $this->validate([
            'openingBalance' => ['required', 'numeric', 'min:0'],
        ], [
            'openingBalance.required' => 'أدخل الرصيد الافتتاحي',
            'openingBalance.numeric' => 'الرصيد الافتتاحي يجب أن يكون رقماً',
            'openingBalance.min' => 'الرصيد الافتتاحي لا يمكن أن يكون سالباً',
        ]);

        $opened = DB::transaction(function () {
            $exists = CashSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                return false;
            }

            CashSession::create([
                'user_id' => Auth::id(),
                'opening_balance' => $this->openingBalance,
                'opened_at' => now(),
                'status' => 'open',
            ]);

            return true;
        });

        if (! $opened) {
            Notification::make()->title('لديك جلسة مفتوحة بالفعل')->warning()->send();
            return;
        }

        $this->reset('openingBalance');

        Notification::make()
            ->title('تم فتح الجلسة')
            ->body('يمكنك الآن البيع من شاشة نقطة البيع')
            ->success()
            ->send();
    }

    public function closeSession(): void
    {
        $this->validate([
            'countedCash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'countedCash.required' => 'أدخل مبلغ النقد الفعلي الموجود في الدرج',
            'countedCash.numeric' => 'المبلغ يجب أن يكون رقماً',
            'countedCash.min' => 'المبلغ لا يمكن أن يكون سالباً',
        ]);

        $difference = DB::transaction(function () {
            $session = CashSession::where('user_id', Auth::id())
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $session) {
                return null;
            }

            $stats = $this->sessionStats($session);
            $counted = round((float) $this->countedCash, 2);
            $diff = round($counted - $stats['expected'], 2);

            $session->update([
                'closing_balance' => $counted,
                'expected_balance' => $stats['expected'],
                'difference' => $diff,
                'notes' => $this->notes ?: null,
                'closed_at' => now(),
                'status' => 'closed',
            ]);

            return $diff;
        });

        if ($difference === null) {
            Notification::make()->title('لا توجد جلسة مفتوحة لإغلاقها')->warning()->send();
            return;
        }

        $this->reset('countedCash', 'notes');

        $notification = Notification::make()->title('تم إغلاق الجلسة');

        if ($difference == 0) {
            $notification->body('الصندوق مطابق تماماً')->success();
        } elseif ($difference > 0) {
            $notification->body('يوجد زيادة في الصندوق بمقدار ' . \App\Support\Money::format($difference))->warning();
        } else {
            $notification->body('يوجد عجز في الصندوق بمقدار ' . \App\Support\Money::format(abs($difference)))->danger();
        }

        $notification->send();
    }
}
