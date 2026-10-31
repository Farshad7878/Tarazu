<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'ایجاد پشتیبان خودکار از داده‌ها و نگه‌داشت آخرین نسخه‌ها طبق config/backup.php';

    public function handle(BackupService $backups, AuditLogger $audit): int
    {
        $result = $backups->create('auto');

        $audit->note('backup_created', "پشتیبان خودکار: {$result['name']}", ['size' => $result['size']]);

        $this->info("پشتیبان ایجاد شد: {$result['name']} ({$result['rows']} ردیف)");

        return self::SUCCESS;
    }
}
