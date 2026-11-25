<?php

namespace Tests\Feature;

use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\StockTakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\Builds;
use Tests\TestCase;

class StockTakeTest extends TestCase
{
    use RefreshDatabase, Builds;

    private function newTake(array $rows): StockTake
    {
        $take = StockTake::create([
            'number' => 'STK-T' . uniqid(),
            'warehouse_id' => Warehouse::defaultOrCreate()->id,
            'status' => 'draft',
        ]);

        foreach ($rows as [$product, $system, $counted]) {
            StockTakeItem::create([
                'stock_take_id' => $take->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'system_qty' => $system,
                'counted_qty' => $counted,
                'cost_price' => $product->cost_price,
            ]);
        }

        return $take;
    }

    public function test_approval_adjusts_differences_and_values_them_at_cost(): void
    {
        $this->actingAs($this->makeUser());
        $short = $this->makeProduct(['cost_price' => 5]);
        $over = $this->makeProduct(['cost_price' => 2]);
        $same = $this->makeProduct();
        foreach ([$short, $over, $same] as $p) {
            $this->stock($p, 10);
        }

        $take = $this->newTake([[$short, 10, 8], [$over, 10, 11.5], [$same, 10, 10]]);

        $value = app(StockTakeService::class)->approve($take);

        $this->assertMoney(8, $this->qtyIn($short));
        $this->assertMoney(11.5, $this->qtyIn($over));
        $this->assertMoney(10, $this->qtyIn($same));
        $this->assertMoney(-7, $value);                      // -2×5 + 1.5×2
        $this->assertSame('approved', $take->fresh()->status);
        $this->assertMoney(-7, $take->fresh()->difference_value);
    }

    public function test_uncounted_items_are_left_untouched(): void
    {
        $this->actingAs($this->makeUser());
        $p = $this->makeProduct();
        $this->stock($p, 10);

        app(StockTakeService::class)->approve($this->newTake([[$p, 10, null]]));

        $this->assertMoney(10, $this->qtyIn($p));
    }

    public function test_sales_made_during_the_count_are_not_lost(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $p = $this->makeProduct(['retail_price' => 10]);
        $this->stock($p, 10);

        $take = $this->newTake([[$p, 10, 9]]);       // العدّ وجد 9 والنظام 10 لحظة البدء

        app(CheckoutService::class)->checkout([$p->id => 3], [], $shift, $user->id);   // بيع 3 أثناء الجرد

        app(StockTakeService::class)->approve($take);

        $this->assertMoney(6, $this->qtyIn($p));     // 10 − 1 (فرق الجرد) − 3 (المبيع)
    }

    public function test_cannot_approve_twice(): void
    {
        $this->actingAs($this->makeUser());
        $p = $this->makeProduct();
        $this->stock($p, 10);
        $take = $this->newTake([[$p, 10, 9]]);
        $svc = app(StockTakeService::class);
        $svc->approve($take);

        try {
            $svc->approve($take);
            $this->fail('كان يجب رفض اعتماد الجرد مرتين');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('معتمد مسبقاً', $e->getMessage());
        }

        $this->assertMoney(9, $this->qtyIn($p));    // لم يُخصم مرتين
    }

    public function test_failed_approval_rolls_back_everything(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $shift = $this->openShift($user);
        $a = $this->makeProduct(['retail_price' => 10]);
        $b = $this->makeProduct(['retail_price' => 10]);
        $this->stock($a, 10);
        $this->stock($b, 10);

        // الصنف ب: النظام 10 والعدّ 0 (فرق −10) لكن بيع 3 بعد البدء فلن يكفي الرصيد
        $take = $this->newTake([[$a, 10, 9], [$b, 10, 0]]);
        app(CheckoutService::class)->checkout([$b->id => 3], [], $shift, $user->id);

        try {
            app(StockTakeService::class)->approve($take);
            $this->fail('كان يجب فشل الاعتماد لأن الرصيد لا يكفي');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('الكمية غير كافية', $e->getMessage());
        }

        $this->assertSame('draft', $take->fresh()->status);
        $this->assertMoney(10, $this->qtyIn($a));    // تسوية الصنف أ لم تُطبَّق أيضاً
    }
}
