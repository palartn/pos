<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep= : عدد الأيام لحفظ النسخ القديمة (الافتراضي من الإعدادات)}';

    protected $description = 'إنشاء نسخة احتياطية مضغوطة لقاعدة البيانات وحذف النسخ القديمة';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create();
        } catch (\Throwable $e) {
            $this->error('فشلت النسخة الاحتياطية: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('تم إنشاء النسخة: ' . $result['name'] . ' (' . number_format($result['size'] / 1024, 1) . ' KB)');

        if ($result['copied'] === true) {
            $this->info('تم نسخها أيضاً إلى: ' . config('backup.copy_path'));
        } elseif ($result['copied'] === false) {
            $this->warn('تعذّر النسخ إلى ' . config('backup.copy_path') . ' (تحقق من توصيل القرص/المجلد)');
        }

        $keep = (int) ($this->option('keep') ?: config('backup.keep_days'));
        $deleted = $backups->prune($keep);

        if ($deleted > 0) {
            $this->line("حُذفت {$deleted} نسخة أقدم من {$keep} يوماً");
        }

        return self::SUCCESS;
    }
}
