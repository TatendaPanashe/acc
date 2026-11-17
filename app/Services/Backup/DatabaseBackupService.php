<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Writes gzip-compressed dumps of the application database to the backup folder,
 * prunes old ones and records the outcome of the last run.
 */
class DatabaseBackupService
{
    /** Backup file names this service produces; also guards download/delete against path tricks. */
    public const NAME_PATTERN = '/^[a-z0-9-]+-\d{4}-\d{2}-\d{2}_\d{6}(-\d+)?\.(sql|sqlite)\.gz$/';

    private const STATUS_FILE = '.last-run.json';

    /**
     * Run a backup now. Returns details of the file written.
     *
     * @return array{name: string, size: int, path: string}
     *
     * @throws RuntimeException when a backup is already running or the dump fails
     */
    public function run(string $trigger = 'manual'): array
    {
        $lock = Cache::lock('database-backup', config('backup.timeout') + 60);
        if (!$lock->get()) {
            throw new RuntimeException('A backup is already running. Try again in a few minutes.');
        }

        $started = microtime(true);
        try {
            $backup = $this->dump();
            $this->copyOffsite($backup);
            $pruned = $this->prune();
            $this->writeStatus([
                'ok' => true, 'trigger' => $trigger, 'file' => $backup['name'], 'size' => $backup['size'],
                'seconds' => round(microtime(true) - $started, 1), 'pruned' => $pruned,
            ]);
            Log::info('Database backup written', ['file' => $backup['name'], 'size' => $backup['size'], 'trigger' => $trigger]);

            return $backup;
        } catch (Throwable $e) {
            $this->writeStatus(['ok' => false, 'trigger' => $trigger, 'error' => $e->getMessage()]);
            Log::error('Database backup failed', ['trigger' => $trigger, 'error' => $e->getMessage()]);
            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            $lock->release();
        }
    }

    /** @return list<array{name: string, size: int, created_at: Carbon}> newest first */
    public function all(): array
    {
        $dir = $this->directory();
        if (!is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => preg_match(self::NAME_PATTERN, $file->getFilename()))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->sortByDesc(fn ($b) => $b['created_at']->getTimestamp().$b['name'])
            ->values()
            ->all();
    }

    /** Absolute path of an existing backup, or null if the name is invalid or missing. */
    public function pathFor(string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->pathFor($name);

        return $path !== null && File::delete($path);
    }

    /** @return array<string, mixed>|null */
    public function lastRun(): ?array
    {
        $file = $this->directory().DIRECTORY_SEPARATOR.self::STATUS_FILE;

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    public function directory(): string
    {
        return rtrim(config('backup.path'), DIRECTORY_SEPARATOR);
    }

    /** @return array{name: string, size: int, path: string} */
    private function dump(): array
    {
        $connection = config('backup.connection') ?: config('database.default');
        $config = config("database.connections.{$connection}");
        if (!$config) {
            throw new RuntimeException("Database connection [{$connection}] is not configured.");
        }

        File::ensureDirectoryExists($this->directory(), 0750);

        return match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config),
            'sqlite' => $this->dumpSqlite($config),
            default => throw new RuntimeException("Backups are not supported for the [{$config['driver']}] driver."),
        };
    }

    /** @return array{name: string, size: int, path: string} */
    private function dumpMysql(array $config): array
    {
        $final = $this->newPath('sql');
        $raw = $final.'.tmp';

        $command = [
            $this->mysqldump(),
            '--single-transaction',   // consistent snapshot of InnoDB tables without locking them
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--hex-blob',
            '--default-character-set='.($config['charset'] ?? 'utf8mb4'),
            '--user='.$config['username'],
            '--result-file='.$raw,
        ];
        if (!empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        } else {
            $command[] = '--host='.$config['host'];
            $command[] = '--port='.$config['port'];
        }
        $command[] = $config['database'];

        try {
            // Password goes in the environment, not the command line, so it never shows in `ps`.
            $result = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
                ->timeout(config('backup.timeout'))
                ->run($command);

            if ($result->failed()) {
                throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput() ?: $result->output()));
            }
            if (!is_file($raw) || !str_contains($this->tail($raw), '-- Dump completed')) {
                throw new RuntimeException('mysqldump did not finish writing the dump; the backup was discarded.');
            }

            return $this->compress($raw, $final);
        } finally {
            File::delete($raw);
        }
    }

    /** @return array{name: string, size: int, path: string} */
    private function dumpSqlite(array $config): array
    {
        $database = $config['database'];
        if ($database === ':memory:' || !is_file($database)) {
            throw new RuntimeException('The SQLite database file could not be found.');
        }

        return $this->compress($database, $this->newPath('sqlite'));
    }

    /** @return array{name: string, size: int, path: string} */
    private function compress(string $source, string $final): array
    {
        $partial = $final.'.partial';
        $in = fopen($source, 'rb');
        $out = gzopen($partial, 'wb6');
        if (!$in || !$out) {
            throw new RuntimeException('Could not open the backup file for writing.');
        }
        try {
            while (!feof($in)) {
                gzwrite($out, (string) fread($in, 1024 * 1024));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
        rename($partial, $final);
        @chmod($final, 0640);

        return ['name' => basename($final), 'size' => filesize($final), 'path' => $final];
    }

    private function copyOffsite(array $backup): void
    {
        if (!$disk = config('backup.copy_to_disk')) {
            return;
        }
        $stream = fopen($backup['path'], 'rb');
        try {
            if (!Storage::disk($disk)->writeStream('backups/'.$backup['name'], $stream)) {
                throw new RuntimeException("Backup saved locally but copying it to the [{$disk}] disk failed.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** Delete backups past the retention window, always keeping the newest keep_min. Returns names removed. */
    private function prune(): array
    {
        $cutoff = now()->subDays(max(1, config('backup.keep_days')));

        return collect($this->all())
            ->slice(max(1, config('backup.keep_min')))
            ->filter(fn ($b) => $b['created_at']->lt($cutoff))
            ->each(fn ($b) => $this->delete($b['name']))
            ->pluck('name')
            ->values()
            ->all();
    }

    private function newPath(string $type): string
    {
        $base = $this->directory().DIRECTORY_SEPARATOR.(Str::slug(config('app.name')) ?: 'database').'-'.now(config('backup.timezone'))->format('Y-m-d_His');
        $path = "{$base}.{$type}.gz";
        for ($i = 2; file_exists($path); $i++) {
            $path = "{$base}-{$i}.{$type}.gz";
        }

        return $path;
    }

    private function mysqldump(): string
    {
        if ($configured = config('backup.mysqldump')) {
            return $configured;
        }
        $found = (new ExecutableFinder)->find('mysqldump', null, [
            '/opt/homebrew/bin', '/opt/homebrew/opt/mysql-client/bin', '/usr/local/bin',
            '/usr/local/mysql/bin', '/usr/bin', '/bin',
        ]);
        if (!$found) {
            throw new RuntimeException('mysqldump was not found. Install the MySQL client or set BACKUP_MYSQLDUMP_PATH in .env.');
        }

        return $found;
    }

    private function tail(string $file, int $bytes = 512): string
    {
        $handle = fopen($file, 'rb');
        fseek($handle, -min($bytes, filesize($file)), SEEK_END);
        $tail = (string) fread($handle, $bytes);
        fclose($handle);

        return $tail;
    }

    private function writeStatus(array $status): void
    {
        File::ensureDirectoryExists($this->directory(), 0750);
        File::put(
            $this->directory().DIRECTORY_SEPARATOR.self::STATUS_FILE,
            json_encode($status + ['at' => now()->toIso8601String()], JSON_PRETTY_PRINT)
        );
    }
}
