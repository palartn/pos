<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['address', 'notes', 'is_active']);
        });
    }
};
