<?php

namespace App\Support;

class PermissionLabels
{
    public const MAP = [
        'pos.access' => 'الدخول لشاشة البيع',
        'pos.credit_sale' => 'البيع بالآجل',
        'sales.view' => 'عرض فواتير البيع',
        'sales.return' => 'مرتجعات البيع',
        'products.manage' => 'إدارة الأصناف',
        'inventory.manage' => 'المخازن والأرصدة وحركة المخزون',
        'stocktake.manage' => 'الجرد',
        'suppliers.manage' => 'الموردون',
        'purchases.manage' => 'المشتريات ومرتجعاتها',
        'customers.manage' => 'العملاء والديون',
        'cashbox.manage' => 'المصروفات والإيرادات',
        'shifts.manage' => 'ورديات الكاشير',
        'employees.manage' => 'الموظفون',
        'reports.view' => 'التقارير (وعرض كل الفواتير والورديات)',
        'settings.manage' => 'الإعدادات العامة',
        'users.manage' => 'المستخدمون والأدوار',
        'vouchers.view' => 'عرض سندات القبض',
        'vouchers.create' => 'إصدار سندات قبض (استلام دفعات العملاء)',
        'vouchers.cancel' => 'إلغاء سندات القبض (المدير العام)',
        'payvouchers.view' => 'عرض سندات الصرف',
        'payvouchers.create' => 'إصدار سندات صرف (دفع المستحقات للموردين)',
        'payvouchers.cancel' => 'إلغاء سندات الصرف (المدير العام)',
        'sequences.manage' => 'إدارة ترقيم المستندات (المدير العام)',
    ];

    public static function label(string $name): string
    {
        return self::MAP[$name] ?? $name;
    }
}
