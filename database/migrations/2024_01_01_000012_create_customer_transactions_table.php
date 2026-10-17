<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained();
            $table->foreignId('cash_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('type', 20);                       // debt | payment
            $table->decimal('amount', 12, 2);                 // دائماً موجب
            $table->decimal('balance_after', 12, 2);          // رصيد العميل بعد الحركة
            $table->string('payment_method', 30)->nullable(); // للدفعات فقط
            $table->string('reference')->nullable();          // رقم العملية / تحديد الطريقة
            $table->text('description')->nullable();         // الأصناف أو الملاحظة
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
    }
};
