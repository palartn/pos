<?php

namespace App\Filament\Pages;

use App\Services\BackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Throwable;

class BackupsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'النسخ الاحتياطي';
    protected static ?string $title = 'النسخ الاحتياطي لقاعدة البيانات';
    protected static ?string $navigationGroup = 'الإدارة';
    protected static ?string $slug = 'backups';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    #[Computed]
    public function backups(): array
    {
        return app(BackupService::class)->list();
    }

    #[Computed]
    public function directory(): string
    {
        return app(BackupService::class)->directory();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('إنشاء نسخة الآن')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->action(function () {
                    try {
                        $r = app(BackupService::class)->create();
                        app(BackupService::class)->prune((int) config('backup.keep_days'));
                    } catch (Throwable $e) {
                        Notification::make()->title('فشلت النسخة الاحتياطية')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    $n = Notification::make()->title('تم إنشاء النسخة الاحتياطية')
                        ->body($r['name'] . ' (' . number_format($r['size'] / 1024, 1) . ' KB)');

                    $r['copied'] === false
                        ? $n->warning()->body($r['name'] . ' — لكن تعذّر نسخها إلى المسار الإضافي')
                        : $n->success();

                    $n->send();
                }),
        ];
    }

    public function download(string $name)
    {
        try {
            return response()->download(app(BackupService::class)->path($name));
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function remove(string $name): void
    {
        try {
            app(BackupService::class)->delete($name);
            Notification::make()->title('تم حذف النسخة')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
