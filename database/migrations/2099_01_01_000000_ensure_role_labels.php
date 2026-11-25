<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ هذا الملف متأخر عن ترحيل Spatie عمداً، لضمان وجود جدول الأدوار
 * قبل إضافة عمود label (مهم عند التثبيت من الصفر وفي الاختبارات).
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = config('permission.table_names.roles', 'roles');

        if (! Schema::hasTable($roles)) {
            return;
        }

        if (! Schema::hasColumn($roles, 'label')) {
            Schema::table($roles, fn (Blueprint $t) => $t->string('label')->nullable());
        }

        foreach ([
            'super_admin' => 'مدير عام',
            'branch_manager' => 'مدير فرع',
            'cashier' => 'كاشير',
            'storekeeper' => 'أمين مخزن',
            'accountant' => 'محاسب',
        ] as $name => $label) {
            DB::table($roles)->where('name', $name)->whereNull('label')->update(['label' => $label]);
        }
    }

    public function down(): void
    {
        // يُترك العمود (تُسقطه migration المرحلة 8 عند التراجع)
    }
};
