<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->date('hire_date')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->string('period', 7);                       // YYYY-MM
            $table->decimal('base_salary', 12, 2);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('advances', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period']);
        });

        Schema::create('employee_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');                              // advance | bonus | deduction
            $table->decimal('amount', 12, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignId('payroll_id')->nullable()->constrained()->nullOnDelete(); // null = لم تُسوَّ بعد
            $table->foreignId('cash_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'payroll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_transactions');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('employees');
    }
};
