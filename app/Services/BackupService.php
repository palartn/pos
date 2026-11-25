<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    private const NAME_PATTERN = '/^backup-\d{8}-\d{6}\.sql\.gz$/';

    public function directory(): string
    {
        $dir = config('backup.directory') ?: storage_path('app/backups');

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("تعذّر إنشاء مجلد النسخ الاحتياطي: {$dir}");
        }

        return rtrim($dir, "\\/");
    }

    /** @return array{driver:string,host:string,port:string,database:string,username:string,password:string} */
    private function connection(): array
    {
        $c = config('database.connections.' . config('database.default'));

        if (! in_array($c['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('النسخ الاحتياطي مدعوم لقاعدة MySQL / MariaDB فقط');
        }

        return [
            'driver' => $c['driver'],
            'host' => (string) ($c['host'] ?? '127.0.0.1'),
            'port' => (string) ($c['port'] ?? '3306'),
            'database' => (string) $c['database'],
            'username' => (string) ($c['username'] ?? 'root'),
            'password' => (string) ($c['password'] ?? ''),
        ];
    }

    /** يُنشئ نسخة مضغوطة، ويرجع بياناتها */
    public function create(): array
    {
        $c = $this->connection();
        $dir = $this->directory();

        $name = 'backup-' . now()->format('Ymd-His') . '.sql.gz';
        $final = $dir . DIRECTORY_SEPARATOR . $name;
        $tmp = $final . '.tmp.sql';

        $process = new Process([
            config('backup.mysqldump_path'),
            '--host=' . $c['host'],
            '--port=' . $c['port'],
            '--user=' . $c['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file=' . $tmp,
            $c['database'],
        ], null, ['MYSQL_PWD' => $c['password']], null, 900);

        try {
            $process->run();
        } catch (\Throwable $e) {
            @unlink($tmp);

            throw new RuntimeException('تعذّر تشغيل mysqldump، تحقق من MYSQLDUMP_PATH في ملف .env: ' . $e->getMessage());
        }

        if (! $process->isSuccessful() || ! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);

            throw new RuntimeException('فشل mysqldump: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $this->gzip($tmp, $final);
        @unlink($tmp);

        $copied = $this->copyOffsite($final, $name);

        return [
            'name' => $name,
            'path' => $final,
            'size' => filesize($final),
            'copied' => $copied,
        ];
    }

    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        if (! $in || ! $out) {
            throw new RuntimeException('تعذّر ضغط ملف النسخة الاحتياطية');
        }

        while (! feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
    }

    /** نسخة إضافية خارج الجهاز؛ تُرجع null إن لم تُضبط، وtrue/false حسب النجاح */
    private function copyOffsite(string $file, string $name): ?bool
    {
        $copy = config('backup.copy_path');

        if (! $copy) {
            return null;
        }

        try {
            if (! is_dir($copy) && ! @mkdir($copy, 0775, true) && ! is_dir($copy)) {
                throw new RuntimeException("المسار غير متاح: {$copy}");
            }

            if (! @copy($file, rtrim($copy, "\\/") . DIRECTORY_SEPARATOR . $name)) {
                throw new RuntimeException('فشل نسخ الملف');
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('فشلت النسخة الاحتياطية الإضافية: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<int, array{name:string,size:int,created_at:Carbon}> الأحدث أولاً */
    public function list(): array
    {
        $files = [];

        foreach (glob($this->directory() . DIRECTORY_SEPARATOR . 'backup-*.sql.gz') ?: [] as $path) {
            $name = basename($path);

            if (! preg_match(self::NAME_PATTERN, $name)) {
                continue;
            }

            $files[] = [
                'name' => $name,
                'size' => (int) filesize($path),
                'created_at' => Carbon::createFromTimestamp(filemtime($path)),
            ];
        }

        usort($files, fn ($a, $b) => $b['created_at']->timestamp <=> $a['created_at']->timestamp ?: strcmp($b['name'], $a['name']));

        return $files;
    }

    public function latest(): ?array
    {
        return $this->list()[0] ?? null;
    }

    /** مسار آمن لملف داخل مجلد النسخ (يرفض أي اسم غير مطابق) */
    public function path(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidArgumentException('اسم ملف النسخة غير صالح');
        }

        $path = $this->directory() . DIRECTORY_SEPARATOR . $name;

        if (! is_file($path)) {
            throw new RuntimeException('ملف النسخة غير موجود');
        }

        return $path;
    }

    public function delete(string $name): void
    {
        @unlink($this->path($name));
    }

    /** حذف النسخ الأقدم من عدد الأيام المحدد، ويرجع عدد المحذوف */
    public function prune(int $days): int
    {
        $limit = now()->subDays($days);
        $deleted = 0;

        foreach ($this->list() as $b) {
            if ($b['created_at']->lt($limit)) {
                @unlink($this->directory() . DIRECTORY_SEPARATOR . $b['name']);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * استرجاع نسخة (اسم داخل مجلد النسخ، أو مسار كامل لملف .sql.gz / .sql).
     * تُنشأ نسخة أمان تلقائياً قبل الاسترجاع. يرجع اسم نسخة الأمان.
     */
    public function restore(string $fileOrName): string
    {
        $c = $this->connection();

        $source = is_file($fileOrName) ? $fileOrName : $this->path($fileOrName);

        $safety = $this->create();

        $sql = $source;
        $temp = null;

        if (str_ends_with($source, '.gz')) {
            $temp = $this->directory() . DIRECTORY_SEPARATOR . 'restore-' . uniqid() . '.sql';
            $in = gzopen($source, 'rb');
            $out = fopen($temp, 'wb');

            if (! $in || ! $out) {
                throw new RuntimeException('تعذّر فك ضغط ملف النسخة');
            }

            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1024 * 1024));
            }

            gzclose($in);
            fclose($out);
            $sql = $temp;
        }

        $handle = fopen($sql, 'rb');

        $process = new Process([
            config('backup.mysql_path'),
            '--host=' . $c['host'],
            '--port=' . $c['port'],
            '--user=' . $c['username'],
            '--default-character-set=utf8mb4',
            $c['database'],
        ], null, ['MYSQL_PWD' => $c['password']], null, 1800);

        $process->setInput($handle);
        $process->run();

        fclose($handle);

        if ($temp) {
            @unlink($temp);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'فشل الاسترجاع (نسخة الأمان محفوظة باسم ' . $safety['name'] . '): ' . trim($process->getErrorOutput())
            );
        }

        return $safety['name'];
    }
}
