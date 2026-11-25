<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'users.manage';

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?string $modelLabel = 'مستخدم';
    protected static ?string $pluralModelLabel = 'المستخدمون';
    protected static ?int $navigationSort = 1;

    // لا يُحذف المستخدم حفاظاً على سجلات الفواتير والورديات؛ يُعطَّل بدلاً من ذلك
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات المستخدم')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->label('البريد الإلكتروني (اسم الدخول)')
                    ->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                Forms\Components\TextInput::make('password')->label('كلمة المرور')
                    ->password()->revealable()->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'اتركها فارغة لعدم تغييرها' : null),
                Forms\Components\Select::make('roles')->label('الأدوار')
                    ->relationship('roles', 'label')->multiple()->preload()->required(),
                Forms\Components\Toggle::make('is_active')->label('الحساب فعّال')->default(true)
                    ->disabled(fn ($record) => $record?->id === auth()->id())
                    ->helperText('الحساب المعطَّل لا يستطيع تسجيل الدخول'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->label('البريد الإلكتروني')->searchable(),
                Tables\Columns\TextColumn::make('roles.label')->label('الأدوار')->badge(),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('أُنشئ في')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('الحالة')->trueLabel('فعّال')->falseLabel('معطَّل'),
                Tables\Filters\SelectFilter::make('roles')->label('الدور')->relationship('roles', 'label'),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
