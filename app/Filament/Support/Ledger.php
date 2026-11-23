<?php

namespace App\Filament\Support;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use Closure;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

class Ledger
{
    public static function money(mixed $value): string
    {
        return '₪' . number_format((float) $value, 2);
    }

    /** حقول نموذج تسجيل دفعة. $maxBalance: دالة تُرجع أقصى مبلغ مسموح (الرصيد الحالي) */
    public static function paymentFields(Closure $maxBalance): array
    {
        $methods = collect(PaymentMethod::cases())
            ->reject(fn ($m) => $m === PaymentMethod::Credit)
            ->mapWithKeys(fn ($m) => [$m->value => $m->label()])
            ->all();

        return [
            Forms\Components\TextInput::make('amount')
                ->label('المبلغ (₪)')
                ->numeric()->step(0.01)->required()
                ->minValue(0.01)->maxValue($maxBalance)
                ->helperText('لا يمكن أن يتجاوز الرصيد المستحق'),
            Forms\Components\Select::make('payment_method')
                ->label('طريقة الدفع')
                ->options($methods)->default('cash')->required()->live(),
            Forms\Components\TextInput::make('payment_details')
                ->label('تفاصيل الدفع (رقم العملية / المحفظة...)')
                ->visible(fn (Get $get) => PaymentMethod::tryFrom((string) $get('payment_method'))?->needsDetails() ?? false),
            Forms\Components\Textarea::make('description')->label('ملاحظات')->rows(2),
        ];
    }

    public static function debtFields(): array
    {
        return [
            Forms\Components\TextInput::make('amount')
                ->label('المبلغ (₪)')
                ->numeric()->step(0.01)->required()->minValue(0.01),
            Forms\Components\TextInput::make('description')
                ->label('البند / الوصف')
                ->required()->maxLength(255),
        ];
    }

    public static function post(Model $owner, LedgerType $type, array $data): bool
    {
        $extra = ['description' => $data['description'] ?? null];

        if ($type === LedgerType::Payment) {
            $extra['payment_method'] = $data['payment_method'] ?? 'cash';
            $extra['payment_details'] = $data['payment_details'] ?? null;
        }

        try {
            $owner->addTransaction($type, (float) $data['amount'], $extra);
        } catch (RuntimeException|InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title('تم تسجيل العملية')->success()->send();

        return true;
    }
}
