<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Enums\EmployeeTransactionType;
use App\Filament\Support\CashSource;
use App\Filament\Support\Ledger;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use RuntimeException;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'السلف والمكافآت والخصومات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('occurred_on', 'desc')
            ->modifyQueryUsing(fn ($q) => $q->with('payroll'))
            ->columns([
                Tables\Columns\TextColumn::make('occurred_on')->label('التاريخ')->date('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->weight('bold'),
                Tables\Columns\TextColumn::make('description')->label('البيان')->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('payroll.period')->label('سُوّيت في راتب')->placeholder('لم تُسوَّ بعد')
                    ->color(fn ($s) => $s ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\Filter::make('unsettled')->label('غير المسوّاة')->toggle()
                    ->query(fn ($q) => $q->whereNull('payroll_id')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add')
                    ->label('تسجيل سلفة / مكافأة / خصم')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('تسجيل حركة للموظف')
                    ->form([
                        Forms\Components\Select::make('type')->label('النوع')
                            ->options([
                                EmployeeTransactionType::Advance->value => 'سلفة (تخرج نقداً وتُخصم من الراتب)',
                                EmployeeTransactionType::Bonus->value => 'مكافأة / إضافي (تُضاف للراتب)',
                                EmployeeTransactionType::Deduction->value => 'خصم (غياب / جزاء)',
                            ])->required()->live()->selectablePlaceholder(false),
                        Forms\Components\TextInput::make('amount')->label('المبلغ (₪)')
                            ->numeric()->step(0.01)->minValue(0.01)->required(),
                        Forms\Components\DatePicker::make('occurred_on')->label('التاريخ')->default(now())->required(),
                        Forms\Components\TextInput::make('description')->label('البيان')->maxLength(255),
                        CashSource::select()
                            ->visible(fn (Get $get) => $get('type') === EmployeeTransactionType::Advance->value),
                    ])
                    ->action(function (array $data) {
                        try {
                            app(PayrollService::class)->addTransaction(
                                $this->getOwnerRecord(),
                                EmployeeTransactionType::from($data['type']),
                                (float) $data['amount'],
                                $data['description'] ?? null,
                                $data['shift_id'] ?? null,
                                $data['occurred_on'],
                            );
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('تم تسجيل الحركة')->success()->send();
                    }),

                Tables\Actions\Action::make('pay')
                    ->label('صرف راتب شهر')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('صرف راتب')
                    ->form([
                        Forms\Components\Select::make('period')->label('الشهر')
                            ->options(PayrollService::periodOptions())->default(now()->format('Y-m'))
                            ->selectablePlaceholder(false)->required()->live(),
                        Forms\Components\Placeholder::make('preview')->label('الحساب')
                            ->content(function (Get $get) {
                                if (! $get('period')) {
                                    return '—';
                                }

                                $p = app(PayrollService::class)->preview($this->getOwnerRecord(), $get('period'));

                                return 'الأساسي ' . Ledger::money($p['base'])
                                    . ' + مكافآت ' . Ledger::money($p['bonuses'])
                                    . ' − خصومات ' . Ledger::money($p['deductions'])
                                    . ' − سلف ' . Ledger::money($p['advances'])
                                    . ' = الصافي ' . Ledger::money($p['net']);
                            }),
                        CashSource::select(),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                    ])
                    ->action(function (array $data) {
                        try {
                            $payroll = app(PayrollService::class)->pay(
                                $this->getOwnerRecord(), $data['period'], $data['shift_id'] ?? null, $data['notes'] ?? null
                            );
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('تم صرف الراتب')
                            ->body('الصافي: ' . Ledger::money($payroll->net_pay))->success()->send();
                    }),
            ]);
    }
}
