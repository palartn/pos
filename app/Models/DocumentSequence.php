<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['key', 'label', 'prefix', 'padding', 'next_number', 'yearly_reset', 'period'];

    protected $casts = [
        'padding' => 'integer',
        'next_number' => 'integer',
        'yearly_reset' => 'boolean',
        'period' => 'integer',
    ];

    /** الصيغة: البادئة + (السنة- إن كان الترقيم سنوياً) + الرقم المعبّأ بالأصفار */
    public function format(int $number): string
    {
        $year = $this->yearly_reset ? (($this->period ?: (int) now()->format('Y')) . '-') : '';

        return $this->prefix . $year . str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }
}
