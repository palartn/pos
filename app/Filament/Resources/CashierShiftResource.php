<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\CashierShiftResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\CashierShift;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CashierShiftResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'shifts.manage';

    protected static ?string $model = CashierShift::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'الخزينة والورديات';
    protected static ?string $modelLabel = 'وردية';
    protected static ?string $pluralModelLabel = 'ورديات الكاشير';
    protected static ?string $navigationLabel = 'ورديات الكاشير';
    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function isManager(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    // الكاشير يرى ورديّاته فقط، والمدير/المحاسب يرون الكل
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');

        if (! static::isManager()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('الكاشير')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('opened_at')->label('فُتحت')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('closed_at')->label('أُغلقت')->dateTime('Y-m-d H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'open' ? 'مفتوحة' : 'مغلقة')
                    ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('opening_cash')->label('الافتتاحي')
                    ->formatStateUsing(fn ($s) => Ledger::money($s)),
                Tables\Columns\TextColumn::make('counted_cash')->label('المعدود')
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s)),
                Tables\Columns\TextColumn::make('expected_cash')->label('المتوقع')
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s))
                    ->visible(fn () => static::isManager()),
                Tables\Columns\TextColumn::make('difference')->label('الفرق')
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s))
                    ->color(fn ($s) => $s === null || (float) $s == 0 ? null : 'danger')
                    ->visible(fn () => static::isManager()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['open' => 'مفتوحة', 'closed' => 'مغلقة']),
            ])
            ->actions([
                Tables\Actions\Action::make('summary')
                    ->label('ملخص')
                    ->icon('heroicon-o-document-chart-bar')
                    ->visible(fn () => static::isManager())
                    ->modalHeading(fn ($record) => 'ملخص وردية ' . $record->user->name)
                    ->modalContent(fn ($record) => view('filament.shift-summary', [
                        's' => $record->summary(),
                        'shift' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Tables\Actions\Action::make('close')
                    ->label('إغلاق الوردية')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isOpen()
                        && ($record->user_id === auth()->id() || static::isManager()))
                    ->modalHeading('إغلاق الوردية')
                    ->modalDescription('عُدَّ النقد الموجود فعلياً في الدرج وأدخل المبلغ.')
                    ->form([
                        Forms\Components\TextInput::make('counted_cash')->label('النقد الموجود في الدرج (₪)')
                            ->numeric()->step(0.01)->minValue(0)->required(),
                        Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $result = $record->closeWith((float) $data['counted_cash'], $data['notes'] ?? null);

                        if ($result === null) {
                            Notification::make()->title('الوردية مغلقة مسبقاً')->warning()->send();

                            return;
                        }

                        $note = Notification::make()->title('تم إغلاق الوردية');

                        if ($result == 0.0) {
                            $note->body('الصندوق مطابق تماماً')->success();
                        } else {
                            $note->body(($result < 0 ? 'عجز' : 'زيادة') . ' بقيمة ' . Ledger::money(abs($result)))->warning();
                        }

                        $note->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCashierShifts::route('/')];
    }
}
