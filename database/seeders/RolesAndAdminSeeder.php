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
            'vouchers.view',     // عرض سندات القبض
            'vouchers.create',   // إصدار سندات قبض
            'vouchers.cancel',   // إلغاء سندات القبض (المدير العام)
            'sequences.manage',  // ترقيم المستندات (المدير العام)
            'payvouchers.view',   // عرض سندات الصرف
            'payvouchers.create', // إصدار سندات صرف
            'payvouchers.cancel', // إلغاء سندات الصرف (المدير العام)
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roles = [
            'super_admin' => $permissions,                                                    // مدير عام
            'branch_manager' => array_values(array_diff($permissions, ['users.manage', 'vouchers.cancel', 'sequences.manage', 'payvouchers.cancel'])),    // مدير فرع
            'cashier' => ['pos.access', 'pos.credit_sale', 'shifts.manage', 'sales.view', 'vouchers.create'],   // كاشير
            'storekeeper' => ['products.manage', 'inventory.manage', 'stocktake.manage', 'suppliers.manage', 'purchases.manage'], // أمين مخزن
            'accountant' => ['cashbox.manage', 'reports.view', 'sales.view', 'suppliers.manage', 'purchases.manage', 'customers.manage', 'vouchers.view', 'vouchers.create', 'payvouchers.view', 'payvouchers.create'], // محاسب
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
