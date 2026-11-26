<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // sale | sale_return | receipt_voucher
            $table->string('label');
            $table->string('prefix', 10);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('type')->default('receipt');      // مخصّص للتوسّع (سند صرف لاحقاً)
            $table->foreignId('customer_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('balance_after', 12, 2)->default(0); // دين العميل بعد السند
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_transaction_id')->nullable()->constrained('customer_transactions')->nullOnDelete();
            $table->string('status')->default('active');    // active | cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        // يستمر ترقيم الفواتير والمرتجعات الموجودة (INV-{id}) دون تكرار
        $now = now();

        DB::table('document_sequences')->insert([
            ['key' => 'sale', 'label' => 'فواتير البيع', 'prefix' => 'INV-', 'padding' => 6,
                'next_number' => (int) DB::table('sales')->max('id') + 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'sale_return', 'label' => 'مرتجعات البيع', 'prefix' => 'RET-', 'padding' => 6,
                'next_number' => (int) DB::table('sale_returns')->max('id') + 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'receipt_voucher', 'label' => 'سندات القبض', 'prefix' => 'RV-', 'padding' => 6,
                'next_number' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('document_sequences');
    }
};
