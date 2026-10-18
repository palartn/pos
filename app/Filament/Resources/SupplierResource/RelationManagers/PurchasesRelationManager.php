<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\Purchase;
use App\Support\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchases';

    protected static ?string $title = 'فواتير الشراء';
    protected static ?string $modelLabel = 'فاتورة شراء';
    protected static ?string $pluralModelLabel = 'فواتير الشراء';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable(),

                Tables\Columns\TextColumn::make('supplier_invoice_number')
                    ->label('فاتورة المورد')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->formatStateUsing(fn ($state) => Money::format($state)),

                Tables\Columns\TextColumn::make('paid')
                    ->label('المدفوع عند الشراء')
                    ->formatStateUsing(fn ($state) => Money::format($state)),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('المتبقي وقتها')
                    ->state(fn (Purchase $record) => max(0, round((float) $record->total - (float) $record->paid, 2)))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->defaultSort('purchase_date', 'desc');
    }
}
