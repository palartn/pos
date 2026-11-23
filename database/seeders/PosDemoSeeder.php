<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $cats = collect(['خضار وفواكه', 'ألبان وأجبان', 'مشروبات', 'معلبات', 'منظفات', 'مخبوزات'])
            ->mapWithKeys(fn ($n) => [$n => Category::firstOrCreate(['name' => $n])]);

        $rows = [
            ['خضار وفواكه', 'بندورة', null, 'كغم', true, 2.5, 3.5, 50],
            ['خضار وفواكه', 'خيار', null, 'كغم', true, 2, 3, 40],
            ['خضار وفواكه', 'تفاح', null, 'كغم', true, 4, 6, 30],
            ['ألبان وأجبان', 'حليب 1 لتر', '6281000000011', 'قطعة', false, 4, 5.5, 60],
            ['ألبان وأجبان', 'لبنة 500غ', '6281000000028', 'قطعة', false, 7, 9, 25],
            ['مشروبات', 'مياه 1.5 لتر', '6281000000035', 'قطعة', false, 1, 2, 120],
            ['مشروبات', 'عصير برتقال', '6281000000042', 'قطعة', false, 3, 4.5, 48],
            ['معلبات', 'تونة', '6281000000059', 'قطعة', false, 5, 7, 36],
            ['معلبات', 'فول مدمس', '6281000000066', 'قطعة', false, 2.5, 4, 40],
            ['منظفات', 'سائل جلي', '6281000000073', 'قطعة', false, 6, 9, 20],
            ['مخبوزات', 'خبز عربي', '6281000000080', 'ربطة', false, 2, 3, 80],
        ];

        foreach ($rows as [$cat, $name, $barcode, $unit, $weight, $cost, $price, $stock]) {
            Product::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $cats[$cat]->id,
                    'barcode' => $barcode,
                    'unit' => $unit,
                    'sold_by_weight' => $weight,
                    'cost_price' => $cost,
                    'retail_price' => $price,
                    'stock' => $stock,
                    'min_stock' => 10,
                ]
            );
        }

        Customer::firstOrCreate(['name' => 'عميل تجريبي'], ['phone' => '0599000000']);
    }
}
