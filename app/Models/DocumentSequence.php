<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['key', 'label', 'prefix', 'padding', 'next_number'];

    protected $casts = [
        'padding' => 'integer',
        'next_number' => 'integer',
    ];

    public function format(int $number): string
    {
        return $this->prefix . str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }
}
