<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('allow_decimal')->default(false)->after('symbol');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->default(0)->change();
            $table->decimal('min_quantity', 12, 3)->default(5)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        // الوحدات الشائعة للوزن والحجم تقبل كميات عشرية تلقائياً
        DB::table('units')
            ->whereIn('name', ['كيلو', 'غرام', 'لتر'])
            ->update(['allow_decimal' => true]);
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
            $table->integer('min_quantity')->default(5)->change();
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('allow_decimal');
        });
    }
};
