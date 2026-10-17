<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->decimal('expected_balance', 12, 2)->nullable()->after('closing_balance');
            $table->decimal('difference', 12, 2)->nullable()->after('expected_balance');
            $table->text('notes')->nullable()->after('difference');
        });
    }

    public function down(): void
    {
        Schema::table('cash_sessions', function (Blueprint $table) {
            $table->dropColumn(['expected_balance', 'difference', 'notes']);
        });
    }
};
