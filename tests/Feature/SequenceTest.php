<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\DocumentSequence;
use App\Models\Sale;
use App\Services\CheckoutService;
use App\Services\NumberSequence;
use App\Services\SaleReturnService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class SequenceTest extends TestCase
{
    use RefreshDatabase, Builds;

    public function test_numbers_are_sequential_per_document_type(): void
    {
        $seq = app(NumberSequence::class);

        $this->assertSame('INV-000001', $seq->next('sale'));
        $this->assertSame('INV-000002', $seq->next('sale'));
        $this->assertSame('RET-000001', $seq->next('sale_return'));
        $this->assertSame('RV-000001', $seq->next('receipt_voucher'));
        $this->assertSame('INV-000003', $seq->next('sale'));
    }

    public function test_prefix_padding_and_next_number_are_configurable(): void
    {
        DocumentSequence::where('key', 'sale')->update(['prefix' => 'F-', 'padding' => 4, 'next_number' => 25]);

        $this->assertSame('F-0025', app(NumberSequence::class)->next('sale'));
        $this->assertSame('F-0026', app(NumberSequence::class)->next('sale'));
    }

    public function test_a_failed_operation_does_not_burn_a_number(): void
    {
        $seq = app(NumberSequence::class);

        try {
            DB::transaction(function () use ($seq) {
                $seq->next('sale');
                throw new Exception('فشل بعد حجز الرقم');
            });
        } catch (Exception) {
            // متوقع
        }

        $this->assertSame('INV-000001', $seq->next('sale'));
    }

    public function test_unknown_document_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(NumberSequence::class)->next('unknown_type');
    }

    public function test_failed_checkout_does_not_consume_an_invoice_number(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $p = $this->makeProduct();
        $this->stock($p, 2);
        $checkout = app(CheckoutService::class);

        try {
            $checkout->checkout([$p->id => 5], [], $shift, $user->id);   // مخزون غير كافٍ
            $this->fail('كان يجب فشل البيع');
        } catch (RuntimeException) {
            // متوقع
        }

        $sale = $checkout->checkout([$p->id => 1], [], $shift, $user->id);

        $this->assertSame('INV-000001', $sale->invoice_number);   // لا فجوة في الترقيم
        $this->assertSame(1, Sale::count());
    }

    public function test_sale_return_numbers_use_their_own_sequence(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $p = $this->makeProduct();
        $this->stock($p, 10);

        $sale = app(CheckoutService::class)->checkout([$p->id => 3], [], $shift, $user->id);
        $r1 = app(SaleReturnService::class)->process($sale, [$sale->items()->first()->id => 1]);
        $r2 = app(SaleReturnService::class)->process($sale, [$sale->items()->first()->id => 1]);

        $this->assertSame('INV-000001', $sale->invoice_number);
        $this->assertSame('RET-000001', $r1->return_number);
        $this->assertSame('RET-000002', $r2->return_number);
    }
}
