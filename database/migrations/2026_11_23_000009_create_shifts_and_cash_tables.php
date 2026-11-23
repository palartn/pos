<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();   // المعدود - المتوقع
            $table->string('status')->default('open');          // open | closed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                              // income | expense
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->date('occurred_on');
            $table->timestamps();
            $table->index('occurred_on');
        });

        foreach (['sales', 'customer_transactions', 'supplier_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['supplier_transactions', 'customer_transactions', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('shift_id');
            });
        }

        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cashier_shifts');
    }
};
