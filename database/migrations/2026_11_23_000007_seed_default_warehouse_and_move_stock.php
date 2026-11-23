<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $warehouseId = DB::table('warehouses')->where('is_default', true)->value('id')
            ?? DB::table('warehouses')->insertGetId([
                'name' => 'المخزن الرئيسي',
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        // ترحيل أي رصيد قديم في عمود products.stock إلى المخزن الرئيسي
        DB::table('products')->where('stock', '>', 0)->orderBy('id')->each(function ($p) use ($warehouseId, $now) {
            if (DB::table('product_stocks')->where('product_id', $p->id)->exists()) {
                return;
            }

            DB::table('product_stocks')->insert([
                'product_id' => $p->id,
                'warehouse_id' => $warehouseId,
                'quantity' => $p->stock,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('stock_movements')->insert([
                'product_id' => $p->id,
                'warehouse_id' => $warehouseId,
                'type' => 'opening',
                'quantity' => $p->stock,
                'balance_after' => $p->stock,
                'reference' => 'ترحيل الرصيد السابق',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        // لا شيء: البيانات تبقى
    }
};
