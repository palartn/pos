<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class BasicDataSeeder extends Seeder
{
    public function run(): void
    {
        // [الاسم، الرمز، يقبل كميات عشرية]
        $units = [
            ['قطعة', 'قطعة', false],
            ['كيلو', 'كغ', true],
            ['غرام', 'غ', true],
            ['لتر', 'لتر', true],
            ['علبة', 'علبة', false],
            ['كيس', 'كيس', false],
            ['كرتونة', 'كرتونة', false],
            ['دزينة', 'دزينة', false],
        ];

        foreach ($units as [$name, $symbol, $allowDecimal]) {
            Unit::firstOrCreate(
                ['name' => $name],
                ['symbol' => $symbol, 'allow_decimal' => $allowDecimal]
            );
        }

        $categories = [
            'مواد غذائية',
            'مشروبات',
            'ألبان وأجبان',
            'خضار وفواكه',
            'لحوم ودواجن',
            'مخبوزات',
            'معلبات',
            'حلويات وسناكس',
            'منظفات',
            'أدوات شخصية',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
