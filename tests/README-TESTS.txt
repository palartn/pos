تشغيل الاختبارات:
  php artisan test
  php artisan test --filter=CheckoutTest

الاختبارات تعمل على SQLite في الذاكرة (الإعداد الافتراضي في phpunit.xml)، ولا تلمس قاعدة بياناتك الحقيقية.
إن ظهر خطأ "could not find driver" فعّل في C:\xampp\php\php.ini السطرين:
  extension=pdo_sqlite
  extension=sqlite3
ثم أعد تشغيل الطرفية.
