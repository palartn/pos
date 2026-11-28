<?php

namespace Tests\Feature;

use App\Models\DocumentSequence;
use App\Models\Sale;
use App\Services\NumberSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Builds;
use Tests\TestCase;

class YearlySequenceTest extends TestCase
{
    use RefreshDatabase, Builds;

    private function year(): int
    {
        return (int) now()->format('Y');
    }

    public function test_yearly_format_includes_the_year(): void
    {
        DocumentSequence::where('key', 'sale')->update(['yearly_reset' => true, 'period' => $this->year(), 'next_number' => 1]);

        $seq = app(NumberSequence::class);

        $this->assertSame('INV-' . $this->year() . '-000001', $seq->next('sale'));
        $this->assertSame('INV-' . $this->year() . '-000002', $seq->next('sale'));
    }

    public function test_counter_restarts_at_one_in_a_new_year(): void
    {
        DocumentSequence::where('key', 'sale')->update([
            'yearly_reset' => true, 'period' => $this->year() - 1, 'next_number' => 57,
        ]);

        $seq = app(NumberSequence::class);

        $this->assertSame('INV-' . $this->year() . '-000001', $seq->next('sale'));
        $this->assertSame('INV-' . $this->year() . '-000002', $seq->next('sale'));
        $this->assertSame($this->year(), DocumentSequence::where('key', 'sale')->value('period'));
    }

    public function test_counter_continues_within_the_same_year(): void
    {
        DocumentSequence::where('key', 'receipt_voucher')->update(['yearly_reset' => true, 'period' => $this->year(), 'next_number' => 41]);

        $this->assertSame('RV-' . $this->year() . '-000041', app(NumberSequence::class)->next('receipt_voucher'));
    }

    public function test_non_yearly_sequences_never_reset(): void
    {
        DocumentSequence::where('key', 'sale')->update(['yearly_reset' => false, 'period' => 2020, 'next_number' => 500]);

        $this->assertSame('INV-000500', app(NumberSequence::class)->next('sale'));
    }

    public function test_an_already_used_number_is_skipped_instead_of_duplicated(): void
    {
        $user = $this->makeUser();

        Sale::create([
            'invoice_number' => 'INV-000001', 'user_id' => $user->id,
            'subtotal' => 10, 'total' => 10, 'paid' => 10, 'payment_method' => 'cash',
        ]);

        // العدّاد يشير إلى رقم مستخدم (مثلاً بعد تغيير الإعدادات)
        DocumentSequence::where('key', 'sale')->update(['next_number' => 1]);

        $seq = app(NumberSequence::class);

        $this->assertSame('INV-000002', $seq->next('sale'));
        $this->assertSame(3, DocumentSequence::where('key', 'sale')->value('next_number'));
    }
}
