<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('supplier_invoice_no')->nullable();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('purchase_date');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('return_date');
            $table->decimal('total', 12, 2);
            $table->decimal('credited', 12, 2)->default(0); // خُصم من مستحقات المورد
            $table->decimal('refunded', 12, 2)->default(0); // أُعيد نقداً من المورد
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
