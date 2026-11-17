<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use RuntimeException;

/** Dumps the database to a compressed backup file. Runs nightly via the scheduler; can be run any time by hand. */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--trigger=manual : Recorded with the run (e.g. scheduled)}';

    protected $description = 'Back up the database to storage/app/private/backups and prune old backups';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $backup = $backups->run($this->option('trigger'));
        } catch (RuntimeException $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup written: %s (%s KB)', $backup['path'], number_format($backup['size'] / 1024, 1)));

        return self::SUCCESS;
    }
}
