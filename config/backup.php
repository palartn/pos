<?php

return [
    // مجلد حفظ النسخ (الافتراضي storage/app/backups)
    'directory' => env('BACKUP_DIRECTORY'),

    // عدد الأيام التي تُحفظ فيها النسخ القديمة قبل حذفها تلقائياً
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    // نسخة إضافية تُنسخ إلى هذا المسار (فلاشة / قرص خارجي / مجلد مزامنة سحابية). اتركه فارغاً لتعطيله
    'copy_path' => env('BACKUP_COPY_PATH'),

    // مسارات أدوات MySQL (في XAMPP: C:\xampp\mysql\bin\mysqldump.exe)
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('MYSQL_PATH', 'mysql'),
];
