<?php

namespace App\Models;

use App\Models\Concerns\HasLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasLedger;

    protected $fillable = ['name', 'company', 'phone', 'address', 'balance', 'notes', 'is_active'];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }
}
