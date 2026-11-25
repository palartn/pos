<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'الإعدادات العامة';
    protected static ?string $title = 'الإعدادات العامة';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?string $slug = 'settings';
    protected static ?int $navigationSort = 3;
    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'store_name' => Setting::get('store_name', ''),
            'store_address' => Setting::get('store_address', ''),
            'store_phone' => Setting::get('store_phone', ''),
            'tax_number' => Setting::get('tax_number', ''),
            'tax_rate' => Setting::get('tax_rate', 0),
            'receipt_width' => Setting::get('receipt_width', '80'),
            'receipt_footer' => Setting::get('receipt_footer', 'شكراً لزيارتكم'),
            'auto_print_receipt' => Setting::bool('auto_print_receipt', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('بيانات المتجر (تظهر على الفاتورة)')->schema([
                    Forms\Components\TextInput::make('store_name')->label('اسم المتجر')->required()->maxLength(120),
                    Forms\Components\TextInput::make('store_phone')->label('هاتف المتجر')->maxLength(50),
                    Forms\Components\TextInput::make('store_address')->label('العنوان')->maxLength(200)->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('الضريبة')->schema([
                    Forms\Components\TextInput::make('tax_number')->label('الرقم الضريبي')->maxLength(50),
                    Forms\Components\TextInput::make('tax_rate')->label('نسبة ضريبة القيمة المضافة (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->default(0)
                        ->helperText('أسعار الأصناف تُعتبر شاملة الضريبة؛ تظهر قيمة الضريبة المستخرجة من الإجمالي على الفاتورة. اتركها 0 لإخفائها.'),
                ])->columns(2),

                Forms\Components\Section::make('طباعة الفاتورة')->schema([
                    Forms\Components\Select::make('receipt_width')->label('عرض ورق الطابعة الحرارية')
                        ->options(['58' => '58 مم', '80' => '80 مم'])->selectablePlaceholder(false)->required(),
                    Forms\Components\Toggle::make('auto_print_receipt')->label('طباعة الفاتورة تلقائياً بعد كل عملية بيع')
                        ->helperText('تظهر نافذة الطباعة بعد البيع. لطباعة صامتة بلا نافذة شغّل المتصفح بخيار kiosk-printing.'),
                    Forms\Components\Textarea::make('receipt_footer')->label('نص أسفل الفاتورة')->rows(2)->columnSpanFull(),
                ])->columns(2),
            ]);
    }

    public function save(): void
    {
        Setting::put($this->form->getState());

        Notification::make()->title('تم حفظ الإعدادات')->success()->send();
    }
}
