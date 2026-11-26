<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('price_level', 10)->default('retail');   // retail | wholesale
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('price_level', 10)->default('retail');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('price_level'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('price_level'));
    }
};
