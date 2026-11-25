<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'pos.access',        // الدخول لشاشة البيع
            'pos.credit_sale',   // البيع بالآجل
            'sales.view',        // عرض فواتير البيع
            'sales.return',      // مرتجعات البيع
            'products.manage',   // الأصناف
            'inventory.manage',  // المخزون
            'stocktake.manage',  // الجرد
            'suppliers.manage',  // الموردون
            'purchases.manage',  // المشتريات
            'customers.manage',  // العملاء والديون
            'cashbox.manage',    // الخزينة والمصروفات
            'shifts.manage',     // جلسات الكاشير
            'employees.manage',  // الموظفون
            'reports.view',      // التقارير
            'settings.manage',   // الإعدادات
            'users.manage',      // المستخدمون والصلاحيات
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'super_admin' => $permissions,                                                    // مدير عام
            'branch_manager' => array_values(array_diff($permissions, ['users.manage'])),    // مدير فرع
            'cashier' => ['pos.access', 'pos.credit_sale', 'shifts.manage', 'sales.view'],   // كاشير
            'storekeeper' => ['products.manage', 'inventory.manage', 'stocktake.manage', 'suppliers.manage', 'purchases.manage'], // أمين مخزن
            'accountant' => ['cashbox.manage', 'reports.view', 'sales.view', 'suppliers.manage', 'purchases.manage', 'customers.manage'], // محاسب
        ];

        $labels = [
            'super_admin' => 'مدير عام',
            'branch_manager' => 'مدير فرع',
            'cashier' => 'كاشير',
            'storekeeper' => 'أمين مخزن',
            'accountant' => 'محاسب',
        ];

        foreach ($roles as $role => $perms) {
            Role::updateOrCreate(['name' => $role, 'guard_name' => 'web'], ['label' => $labels[$role]])->syncPermissions($perms);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@supermarket.test'],
            ['name' => 'المدير العام', 'password' => 'ChangeMe@123']
        );
        $admin->assignRole('super_admin');
    }
}
