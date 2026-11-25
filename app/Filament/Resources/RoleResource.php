<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\RoleResource\Pages;
use App\Support\PermissionLabels;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'users.manage';

    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?string $modelLabel = 'دور';
    protected static ?string $pluralModelLabel = 'الأدوار والصلاحيات';
    protected static ?string $navigationLabel = 'الأدوار والصلاحيات';
    protected static ?int $navigationSort = 2;

    // المدير العام ثابت ولا يُعدَّل
    public static function canEdit(Model $record): bool
    {
        return static::allowed() && $record->name !== 'super_admin';
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed()
            && $record->name !== 'super_admin'
            && ! $record->users()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الدور')->schema([
                Forms\Components\TextInput::make('label')->label('اسم الدور (يظهر في الواجهة)')->required()->maxLength(100),
                Forms\Components\TextInput::make('name')->label('المعرّف (إنجليزي)')
                    ->required()->maxLength(50)->regex('/^[a-z0-9_]+$/')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($record) => $record !== null)
                    ->helperText('أحرف إنجليزية صغيرة وأرقام و _ فقط، ولا يتغير بعد الإنشاء'),
                Forms\Components\Hidden::make('guard_name')->default('web'),
            ])->columns(2),

            Forms\Components\Section::make('الصلاحيات')->schema([
                Forms\Components\CheckboxList::make('permissions')
                    ->hiddenLabel()
                    ->relationship(titleAttribute: 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => PermissionLabels::label($record->name))
                    ->columns(3)->bulkToggleable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('الدور')->weight('bold')->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('المعرّف')->color('gray'),
                Tables\Columns\TextColumn::make('permissions_count')->label('عدد الصلاحيات')->counts('permissions'),
                Tables\Columns\TextColumn::make('users_count')->label('عدد المستخدمين')->counts('users'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
