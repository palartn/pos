<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        // اسم عرض عربي للأدوار
        $roles = config('permission.table_names.roles', 'roles');

        if (Schema::hasTable($roles)) {
            Schema::table($roles, function (Blueprint $table) {
                $table->string('label')->nullable();
            });

            foreach ([
                'super_admin' => 'مدير عام',
                'branch_manager' => 'مدير فرع',
                'cashier' => 'كاشير',
                'storekeeper' => 'أمين مخزن',
                'accountant' => 'محاسب',
            ] as $name => $label) {
                DB::table($roles)->where('name', $name)->update(['label' => $label]);
            }
        }
    }

    public function down(): void
    {
        $roles = config('permission.table_names.roles', 'roles');

        if (Schema::hasTable($roles) && Schema::hasColumn($roles, 'label')) {
            Schema::table($roles, fn (Blueprint $t) => $t->dropColumn('label'));
        }

        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
        Schema::dropIfExists('settings');
    }
};
