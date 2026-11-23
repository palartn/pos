<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * يربط صلاحيات الـ Resource باسم صلاحية واحد:
 * protected static string $permission = 'customers.manage';
 */
trait RequiresPermission
{
    protected static function allowed(): bool
    {
        return auth()->user()?->can(static::$permission) ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::allowed();
    }

    public static function canCreate(): bool
    {
        return static::allowed();
    }

    public static function canView(Model $record): bool
    {
        return static::allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed();
    }
}
