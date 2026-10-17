# تحديث: شاشة الفواتير + طباعة الإيصال

## الملفات (انسخها فوق مشروعك بنفس المسارات)

- routes/web.php                                              (يستبدل الحالي: يحتوي التحويل لـ /admin + مسار الإيصال)
- config/store.php                                            (يستبدل الحالي: أضيفت العنوان والهاتف ومقاس الإيصال ونص التذييل)
- app/Http/Controllers/ReceiptController.php                  (جديد)
- resources/views/receipts/show.blade.php                     (جديد: تصميم الإيصال)
- app/Filament/Resources/SaleResource.php + SaleResource/Pages (جديد: شاشة الفواتير)
- app/Filament/Pages/PosScreen.php                            (محدّث)
- resources/views/filament/pages/pos-screen.blade.php         (محدّث)

يجب تطبيقه بعد تحديث العملاء (customers-update).

## قبل النسخ

- إن كان لديك مسارات أخرى في routes/web.php فادمجها يدوياً بدل الاستبدال.
- في config/store.php ضع اسم متجرك وعنوانه ورقم هاتفه ونص التذييل.

## التشغيل

```powershell
php artisan route:clear
php artisan optimize:clear
```

## الاستخدام

- بعد كل عملية بيع يظهر في شاشة البيع شريط "آخر فاتورة" مع زر طباعة الإيصال.
- من قائمة المبيعات > الفواتير: عرض التفاصيل وطباعة أي فاتورة قديمة.
- في صفحة الإيصال أزرار لتبديل المقاس: 80 مم، 58 مم، A4. والمقاس الافتراضي من config/store.php.
- عند ظهور نافذة الطباعة اختر طابعة الإيصالات، وأوقف الهوامش (Margins: None) وإيقاف الرأس والتذييل.
