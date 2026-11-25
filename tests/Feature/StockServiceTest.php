<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase, Builds;

    public function test_adjust_updates_warehouse_stock_and_product_total(): void
    {
        $p = $this->makeProduct();
        $w = Warehouse::defaultOrCreate();
        $svc = app(StockService::class);

        $svc->adjust($p->id, $w->id, 10, 'opening');
        $svc->adjust($p->id, $w->id, -3.5, 'sale', 'INV-1');

        $this->assertMoney(6.5, $this->qtyIn($p));
        $this->assertMoney(6.5, $p->fresh()->stock);
        $this->assertSame(2, StockMovement::where('product_id', $p->id)->count());

        $last = StockMovement::where('product_id', $p->id)->latest('id')->first();
        $this->assertMoney(-3.5, $last->quantity);
        $this->assertMoney(6.5, $last->balance_after);
    }

    public function test_cannot_go_negative_and_nothing_is_recorded(): void
    {
        $p = $this->makeProduct();
        $this->stock($p, 5);
        $movementsBefore = StockMovement::count();

        try {
            app(StockService::class)->adjust($p->id, Warehouse::defaultOrCreate()->id, -6, 'sale');
            $this->fail('كان يجب رفض الخصم الذي يجعل الرصيد سالباً');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('الكمية غير كافية', $e->getMessage());
        }

        $this->assertMoney(5, $this->qtyIn($p));
        $this->assertMoney(5, $p->fresh()->stock);
        $this->assertSame($movementsBefore, StockMovement::count());
    }

    public function test_transfer_moves_quantity_between_warehouses_and_keeps_total(): void
    {
        $p = $this->makeProduct();
        $w1 = Warehouse::defaultOrCreate();
        $w2 = Warehouse::create(['name' => 'مخزن ثانٍ']);
        $this->stock($p, 10, $w1);

        app(StockService::class)->transfer($p->id, $w1->id, $w2->id, 4);

        $this->assertMoney(6, $this->qtyIn($p, $w1));
        $this->assertMoney(4, $this->qtyIn($p, $w2));
        $this->assertMoney(10, $p->fresh()->stock);
    }

    public function test_transfer_more_than_available_changes_nothing(): void
    {
        $p = $this->makeProduct();
        $w1 = Warehouse::defaultOrCreate();
        $w2 = Warehouse::create(['name' => 'مخزن ثانٍ']);
        $this->stock($p, 3, $w1);

        $this->expectException(RuntimeException::class);

        try {
            app(StockService::class)->transfer($p->id, $w1->id, $w2->id, 5);
        } finally {
            $this->assertMoney(3, $this->qtyIn($p, $w1));
            $this->assertMoney(0, $this->qtyIn($p, $w2));
            $this->assertMoney(3, $p->fresh()->stock);
        }
    }

    public function test_transfer_to_same_warehouse_is_rejected(): void
    {
        $p = $this->makeProduct();
        $w = Warehouse::defaultOrCreate();
        $this->stock($p, 3, $w);

        $this->expectException(RuntimeException::class);
        app(StockService::class)->transfer($p->id, $w->id, $w->id, 1);
    }

    public function test_product_total_always_equals_sum_of_warehouse_stocks(): void
    {
        $p = $this->makeProduct();
        $w1 = Warehouse::defaultOrCreate();
        $w2 = Warehouse::create(['name' => 'مخزن ثانٍ']);
        $svc = app(StockService::class);

        $svc->adjust($p->id, $w1->id, 12.25, 'opening');
        $svc->adjust($p->id, $w2->id, 7, 'opening');
        $svc->transfer($p->id, $w1->id, $w2->id, 2.5);
        $svc->adjust($p->id, $w2->id, -1.125, 'sale');
        $svc->adjust($p->id, $w1->id, 0.375, 'sale_return');

        $sum = (float) ProductStock::where('product_id', $p->id)->sum('quantity');

        $this->assertMoney($sum, Product::find($p->id)->stock);
    }
}
