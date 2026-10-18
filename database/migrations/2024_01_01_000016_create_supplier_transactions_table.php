<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained();
            $table->foreignId('cash_session_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('type', 20);                        // purchase | payment | opening
            $table->decimal('amount', 12, 2);                  // موقّع: موجب يزيد ما علينا، سالب يُنقصه
            $table->decimal('balance_after', 12, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
    }
};
