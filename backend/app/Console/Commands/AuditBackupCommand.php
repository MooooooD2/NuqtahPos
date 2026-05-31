<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditBackupCommand extends Command
{
    protected $signature = 'audit:backup';

    protected $description = 'نسخ ملفات الـ Audit Log إلى مجلد archive';

    public function handle(): int
    {
        $source = storage_path('logs/audit-' . now()->subDay()->format('Y-m-d') . '.log');
        $dest = storage_path('logs/archive/audit-' . now()->subDay()->format('Y-m-d') . '.log');

        if (File::exists($source)) {
            File::ensureDirectoryExists(storage_path('logs/archive'));
            File::copy($source, $dest);
            $this->info("تم أرشفة: {$dest}");
        }

        return Command::SUCCESS;
    }
}
