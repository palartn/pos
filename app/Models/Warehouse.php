<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['name', 'location', 'is_default', 'is_active', 'notes'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // مخزن افتراضي واحد فقط (يُباع منه في شاشة POS)
        static::saved(function (Warehouse $w) {
            if ($w->is_default) {
                static::where('id', '!=', $w->id)->update(['is_default' => false]);
            }
        });
    }

    public static function defaultOrCreate(): self
    {
        return static::where('is_default', true)->first()
            ?? static::firstOrCreate(['name' => 'المخزن الرئيسي'], ['is_default' => true, 'is_active' => true]);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }
}
