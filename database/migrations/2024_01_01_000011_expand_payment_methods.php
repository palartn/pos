<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // من enum ثابت إلى نص حتى نستطيع إضافة طرق دفع جديدة بدون هجرات
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 30)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            // رقم العملية أو تحديد طريقة الدفع عند اختيار "أخرى"
            $table->string('reference')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        // نترك عمود method كنص (التراجع إلى enum قد يُتلف السجلات الجديدة)
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('reference');
        });
    }
};
