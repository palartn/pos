<?php

namespace App\Filament\Support;

use App\Enums\LedgerType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\PaymentVoucherService;
use App\Services\VoucherService;
use Closure;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Actions\Action as NotificationAction;
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
        // دفعة عميل = سند قبض بترقيم تسلسلي
        if ($owner instanceof Customer && $type === LedgerType::Payment) {
            try {
                $voucher = app(VoucherService::class)->receive(
                    $owner,
                    (float) $data['amount'],
                    $data['payment_method'] ?? 'cash',
                    $data['payment_details'] ?? null,
                    $data['description'] ?? null,
                );
            } catch (RuntimeException|InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return false;
            }

            Notification::make()
                ->title('تم إصدار سند القبض ' . $voucher->number)
                ->body('المبلغ ' . self::money($voucher->amount) . ' — الدين المتبقي ' . self::money($voucher->balance_after))
                ->actions([
                    NotificationAction::make('print')->label('طباعة السند')
                        ->url(route('vouchers.show', $voucher) . '?autoprint=1', shouldOpenInNewTab: true),
                ])
                ->success()->send();

            return true;
        }

        // دفعة مورد = سند صرف بترقيم تسلسلي
        if ($owner instanceof Supplier && $type === LedgerType::Payment) {
            try {
                $voucher = app(PaymentVoucherService::class)->pay(
                    $owner,
                    (float) $data['amount'],
                    $data['payment_method'] ?? 'cash',
                    $data['payment_details'] ?? null,
                    $data['description'] ?? null,
                );
            } catch (RuntimeException|InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();

                return false;
            }

            Notification::make()
                ->title('تم إصدار سند الصرف ' . $voucher->number)
                ->body('المبلغ ' . self::money($voucher->amount) . ' — المستحق المتبقي ' . self::money($voucher->balance_after))
                ->actions([
                    NotificationAction::make('print')->label('طباعة السند')
                        ->url(route('payment-vouchers.show', $voucher) . '?autoprint=1', shouldOpenInNewTab: true),
                ])
                ->success()->send();

            return true;
        }

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
