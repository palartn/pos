<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('balance_after', 12, 2)->default(0); // المستحق للمورد بعد السند
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_transaction_id')->nullable()->constrained('supplier_transactions')->nullOnDelete();
            $table->string('status')->default('active');    // active | cancelled
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        $now = now();

        DB::table('document_sequences')->insertOrIgnore([
            'key' => 'payment_voucher', 'label' => 'سندات الصرف', 'prefix' => 'PV-', 'padding' => 6,
            'next_number' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_vouchers');
        DB::table('document_sequences')->where('key', 'payment_voucher')->delete();
    }
};
