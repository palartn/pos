<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private static function all_(): array
    {
        try {
            return Cache::rememberForever('app.settings', fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all_();

        return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = static::get($key);

        return $v === null ? $default : (bool) (int) $v;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget('app.settings');
    }
}
