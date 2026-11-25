<?php

namespace Tests\Feature;

use App\Services\BackupService;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bk_' . uniqid();
        mkdir($this->dir, 0775, true);
        config(['backup.directory' => $this->dir]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    private function fake(string $name, int $ageDays): string
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, 'x');
        touch($path, time() - $ageDays * 86400);

        return $path;
    }

    public function test_path_rejects_traversal_and_foreign_names(): void
    {
        $svc = app(BackupService::class);

        foreach (['../../.env', 'backup-1.sql.gz', 'backup-20261101-010101.sql', '/etc/passwd', 'backup-20261101-010101.sql.gz/../x'] as $bad) {
            try {
                $svc->path($bad);
                $this->fail("كان يجب رفض الاسم: {$bad}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_path_accepts_valid_existing_file_and_rejects_missing(): void
    {
        $svc = app(BackupService::class);
        $this->fake('backup-20261101-010101.sql.gz', 1);

        $this->assertFileExists($svc->path('backup-20261101-010101.sql.gz'));

        $this->expectException(RuntimeException::class);
        $svc->path('backup-20261102-010101.sql.gz');
    }

    public function test_list_is_newest_first_and_ignores_other_files(): void
    {
        $svc = app(BackupService::class);
        $this->fake('backup-20261001-010101.sql.gz', 50);
        $this->fake('backup-20261120-010101.sql.gz', 2);
        $this->fake('backup-20261110-010101.sql.gz', 10);
        $this->fake('notes.txt', 1);

        $names = array_column($svc->list(), 'name');

        $this->assertSame([
            'backup-20261120-010101.sql.gz',
            'backup-20261110-010101.sql.gz',
            'backup-20261001-010101.sql.gz',
        ], $names);
        $this->assertSame('backup-20261120-010101.sql.gz', $svc->latest()['name']);
    }

    public function test_prune_deletes_only_old_backups(): void
    {
        $svc = app(BackupService::class);
        $old = $this->fake('backup-20260901-010101.sql.gz', 60);
        $recent = $this->fake('backup-20261120-010101.sql.gz', 3);

        $this->assertSame(1, $svc->prune(30));
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    public function test_delete_removes_the_file(): void
    {
        $svc = app(BackupService::class);
        $path = $this->fake('backup-20261120-010101.sql.gz', 3);

        $svc->delete('backup-20261120-010101.sql.gz');

        $this->assertFileDoesNotExist($path);
    }
}
