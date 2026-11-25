<?php

namespace Tests\Support;

use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Database\Seeders\RolesAndAdminSeeder;

/** دوال مساعدة لبناء بيانات الاختبار */
trait Builds
{
    protected function seedRoles(): void
    {
        $this->seed(RolesAndAdminSeeder::class);
    }

    protected function makeUser(?string $role = null, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name' => 'مستخدم ' . uniqid(),
            'email' => uniqid() . '@test.local',
            'password' => 'password',
        ], $attrs));

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function makeProduct(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'name' => 'صنف ' . uniqid(),
            'unit' => 'قطعة',
            'cost_price' => 5,
            'retail_price' => 10,
            'min_stock' => 0,
        ], $attrs));
    }

    protected function stock(Product $product, float $qty, ?Warehouse $warehouse = null): void
    {
        app(StockService::class)->adjust($product->id, ($warehouse ?? Warehouse::defaultOrCreate())->id, $qty, 'opening');
    }

    protected function qtyIn(Product $product, ?Warehouse $warehouse = null): float
    {
        return (float) ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', ($warehouse ?? Warehouse::defaultOrCreate())->id)
            ->value('quantity');
    }

    protected function openShift(User $user, float $opening = 0): CashierShift
    {
        return CashierShift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => $opening,
            'status' => 'open',
        ]);
    }

    protected function makeCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge(['name' => 'عميل ' . uniqid()], $attrs));
    }

    protected function makeSupplier(array $attrs = []): Supplier
    {
        return Supplier::create(array_merge(['name' => 'مورد ' . uniqid()], $attrs));
    }

    protected function assertMoney(float $expected, mixed $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.005, $message);
    }
}
