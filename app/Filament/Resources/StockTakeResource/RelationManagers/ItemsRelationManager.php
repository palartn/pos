<?php

namespace App\Filament\Resources\StockTakeResource\RelationManagers;

use App\Filament\Support\Ledger;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'أصناف الجرد (أدخل الكمية الفعلية)';

    public function isReadOnly(): bool
    {
        return false;
    }

    private static function fmt(?float $v): string
    {
        if ($v === null) {
            return '—';
        }

        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return ($s === '' || $s === '-0') ? '0' : $s;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->defaultSort('product_name')
            ->description(function () {
                $total = $this->getOwnerRecord()->items()->count();
                $counted = $this->getOwnerRecord()->items()->whereNotNull('counted_qty')->count();

                return "تم جرد {$counted} من {$total} صنف";
            })
            ->columns([
                Tables\Columns\TextColumn::make('product_name')->label('الصنف')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('barcode')->label('الباركود')->searchable()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('system_qty')->label('رصيد النظام')
                    ->formatStateUsing(fn ($s) => self::fmt((float) $s)),
                Tables\Columns\TextInputColumn::make('counted_qty')->label('الكمية الفعلية')
                    ->type('number')->step(0.001)->rules(['nullable', 'numeric', 'min:0'])
                    ->disabled(fn () => ! $this->getOwnerRecord()->isDraft())
                    ->updateStateUsing(function ($record, $state) {
                        $record->update(['counted_qty' => ($state === null || $state === '') ? null : round((float) $state, 3)]);

                        return $state;
                    }),
                Tables\Columns\TextColumn::make('diff')->label('الفرق')
                    ->state(fn ($record) => $record->difference())
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : (($s > 0 ? '+' : '') . self::fmt((float) $s)))
                    ->color(fn ($s) => $s === null || (float) $s == 0 ? null : ((float) $s < 0 ? 'danger' : 'success'))
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('diff_value')->label('قيمة الفرق')
                    ->state(fn ($record) => $record->difference() === null ? null : $record->difference() * (float) $record->cost_price)
                    ->formatStateUsing(fn ($s) => $s === null ? '—' : Ledger::money($s))
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('uncounted')->label('لم تُجرد بعد')->toggle()
                    ->query(fn (Builder $q) => $q->whereNull('counted_qty')),
                Tables\Filters\Filter::make('has_diff')->label('بها فروقات')->toggle()
                    ->query(fn (Builder $q) => $q->whereNotNull('counted_qty')->whereColumn('counted_qty', '!=', 'system_qty')),
            ])
            ->paginated([25, 50, 100]);
    }
}
