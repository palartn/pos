# مشروع السوبر ماركت: الحزمة الكاملة (كل التحديثات مدموجة)

هذه الحزمة تجمع كل الملفات التي أُرسلت سابقاً بالترتيب الصحيح، لتنسخها مرة واحدة.
لا تحتوي على Laravel نفسه (vendor وغيره)، فهي تُنسخ فوق مشروعك.

## الخطوات

1. فك الضغط وانسخ كل المجلدات (app, config, database, lang, resources, routes) إلى جذر مشروعك
   واختر "استبدال الملفات" عند السؤال.
2. ملفات ستُستبدل ويجب أن تراجعها:
   - config/store.php            ضع فيه اسم متجرك وعنوانه وهاتفه ونص التذييل
   - routes/web.php              إن كان فيه مسارات أخرى فادمجها
   - app/Providers/Filament/AdminPanelProvider.php
3. في ملف .env (إن لم تفعل سابقاً):
   ```
   APP_LOCALE=ar
   APP_FALLBACK_LOCALE=en
   APP_FAKER_LOCALE=ar_SA
   ```
   وفي config/app.php ضع المنطقة الزمنية لمدينتك: 'timezone' => 'Asia/Amman'
4. نفّذ:
   ```powershell
   composer dump-autoload
   php artisan migrate
   php artisan db:seed --class=BasicDataSeeder
   php artisan storage:link
   php artisan optimize:clear
   ```
5. أوقف السيرفر وأعد تشغيله: php artisan serve

الأمر migrate ينفّذ فقط الهجرات الجديدة، ولا يمسّ بياناتك الحالية.
