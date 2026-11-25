<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Support\Ledger;
use App\Models\Payroll;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayrollResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'employees.manage';

    protected static ?string $model = Payroll::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationGroup = 'الموظفون';
    protected static ?string $modelLabel = 'راتب';
    protected static ?string $pluralModelLabel = 'سجل الرواتب';
    protected static ?string $navigationLabel = 'سجل الرواتب';
    protected static ?int $navigationSort = 2;

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

    public static function table(Table $table): Table
    {
        $money = fn ($s) => Ledger::money($s);

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $q) => $q->with('employee'))
            ->columns([
                Tables\Columns\TextColumn::make('period')->label('الشهر')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('employee.name')->label('الموظف')->searchable(),
                Tables\Columns\TextColumn::make('base_salary')->label('الأساسي')->formatStateUsing($money),
                Tables\Columns\TextColumn::make('bonuses')->label('مكافآت')->formatStateUsing($money),
                Tables\Columns\TextColumn::make('deductions')->label('خصومات')->formatStateUsing($money),
                Tables\Columns\TextColumn::make('advances')->label('سلف')->formatStateUsing($money),
                Tables\Columns\TextColumn::make('net_pay')->label('الصافي المدفوع')->formatStateUsing($money)->weight('bold')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('الإجمالي')->formatStateUsing($money)),
                Tables\Columns\TextColumn::make('paid_on')->label('تاريخ الصرف')->date('Y-m-d')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period')->label('الشهر')
                    ->options(fn () => Payroll::query()->distinct()->orderByDesc('period')->pluck('period', 'period')),
                Tables\Filters\SelectFilter::make('employee_id')->label('الموظف')->relationship('employee', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayrolls::route('/')];
    }
}
