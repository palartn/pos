<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            return;
        }

        $perms = ['payvouchers.view', 'payvouchers.create', 'payvouchers.cancel'];

        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        if (Schema::hasTable(config('permission.table_names.roles', 'roles'))) {
            $grants = [
                'super_admin' => $perms,
                'branch_manager' => ['payvouchers.view', 'payvouchers.create'],
                'accountant' => ['payvouchers.view', 'payvouchers.create'],
            ];

            foreach ($grants as $role => $list) {
                Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($list);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // تبقى الصلاحيات
    }
};
