<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RestoreDatabase extends Command
{
    protected $signature = 'db:restore {file : اسم ملف من مجلد النسخ أو مسار كامل} {--force : تنفيذ بدون سؤال}';

    protected $description = 'استرجاع قاعدة البيانات من نسخة احتياطية (يستبدل كل البيانات الحالية)';

    public function handle(BackupService $backups): int
    {
        $file = $this->argument('file');

        $this->warn('تحذير: سيتم استبدال كل بيانات النظام الحالية بمحتوى النسخة: ' . $file);

        if (! $this->option('force') && ! $this->confirm('هل أنت متأكد؟ (تُنشأ نسخة أمان تلقائياً قبل الاسترجاع)')) {
            $this->line('أُلغيت العملية');

            return self::SUCCESS;
        }

        try {
            $safety = $backups->restore($file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        Artisan::call('optimize:clear');

        $this->info('تم الاسترجاع بنجاح. نسخة الأمان قبل الاسترجاع: ' . $safety);

        return self::SUCCESS;
    }
}
