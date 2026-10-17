<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['name', 'symbol', 'allow_decimal'];

    protected $casts = [
        'allow_decimal' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
