<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Filament\Support\CashSource;
use App\Filament\Support\Ledger;
use App\Models\Employee;
use App\Models\User;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class EmployeeResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'employees.manage';

    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'الموظفون';
    protected static ?string $modelLabel = 'موظف';
    protected static ?string $pluralModelLabel = 'الموظفون';
    protected static ?int $navigationSort = 1;

    // لا يُحذف موظف له سجل رواتب
    public static function canDelete(Model $record): bool
    {
        return static::allowed() && ! $record->payrolls()->exists();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الموظف')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('job_title')->label('المسمى الوظيفي')->maxLength(100),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->tel()->maxLength(30),
                Forms\Components\DatePicker::make('hire_date')->label('تاريخ التعيين'),
                Forms\Components\TextInput::make('base_salary')->label('الراتب الأساسي الشهري (₪)')
                    ->numeric()->step(0.01)->minValue(0)->default(0)->required(),
                Forms\Components\Select::make('user_id')->label('حساب الدخول (إن وُجد)')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()->placeholder('لا يوجد'),
                Forms\Components\Toggle::make('is_active')->label('على رأس العمل')->default(true),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum(
                ['transactions as advances_due' => fn ($t) => $t->where('type', 'advance')->whereNull('payroll_id')],
                'amount'
            ))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('job_title')->label('المسمى')->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('base_salary')->label('الراتب الأساسي')
                    ->formatStateUsing(fn ($s) => Ledger::money($s))->sortable(),
                Tables\Columns\TextColumn::make('advances_due')->label('سلف غير مسوّاة')
                    ->formatStateUsing(fn ($s) => (float) $s > 0 ? Ledger::money($s) : '—')
                    ->color(fn ($s) => (float) $s > 0 ? 'warning' : null),
                Tables\Columns\IconColumn::make('is_active')->label('على رأس العمل')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')->trueLabel('على رأس العمل')->falseLabel('متوقف'),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('فتح / تعديل')])
            ->bulkActions([
                Tables\Actions\BulkAction::make('pay_salaries')
                    ->label('صرف رواتب المحددين')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->modalHeading('صرف رواتب شهر')
                    ->form([
                        Forms\Components\Select::make('period')->label('الشهر')
                            ->options(PayrollService::periodOptions())->default(now()->format('Y-m'))
                            ->selectablePlaceholder(false)->required(),
                        CashSource::select(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $service = app(PayrollService::class);
                        $paid = 0;
                        $total = 0.0;
                        $errors = [];

                        foreach ($records as $employee) {
                            try {
                                $payroll = $service->pay($employee, $data['period'], $data['shift_id'] ?? null);
                                $paid++;
                                $total += (float) $payroll->net_pay;
                            } catch (RuntimeException $e) {
                                $errors[] = $e->getMessage();
                            }
                        }

                        if ($paid > 0) {
                            Notification::make()->title("تم صرف {$paid} راتب")
                                ->body('المجموع: ' . Ledger::money($total))->success()->send();
                        }

                        if ($errors) {
                            Notification::make()->title('تعذّر صرف بعض الرواتب')
                                ->body(implode("\n", $errors))->danger()->persistent()->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\TransactionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
