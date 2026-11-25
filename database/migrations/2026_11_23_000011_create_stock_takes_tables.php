<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');           // draft | approved
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('difference_value', 12, 2)->nullable(); // قيمة الفروقات بسعر التكلفة (+ زيادة / - عجز)
            $table->timestamps();
        });

        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('barcode')->nullable();
            $table->decimal('system_qty', 12, 3);            // رصيد النظام لحظة بدء الجرد
            $table->decimal('counted_qty', 12, 3)->nullable(); // الكمية الفعلية (null = لم يُجرد)
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['stock_take_id', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
    }
};
