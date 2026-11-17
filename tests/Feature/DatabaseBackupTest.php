<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-17 23:59:00');
        $this->dir = storage_path('framework/testing/backups-'.uniqid());
        config([
            'backup.path' => $this->dir,
            'backup.timezone' => 'UTC',
            'backup.connection' => 'mysql',
            'backup.mysqldump' => 'mysqldump',
            'database.connections.mysql.password' => 'pa ss',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Fake mysqldump: write a dump to the --result-file it was given. */
    private function fakeDump(bool $complete = true, int $exitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($complete, $exitCode) {
            foreach ($process->command as $arg) {
                if (str_starts_with($arg, '--result-file=')) {
                    file_put_contents(substr($arg, 14), "CREATE TABLE t (id int);\n".($complete ? "-- Dump completed on 2026-11-17\n" : ''));
                }
            }

            return Process::result(errorOutput: $exitCode ? 'Access denied' : '', exitCode: $exitCode);
        });
    }

    private function superuser(bool $super = true): User
    {
        return User::create([
            'name' => 'Admin', 'username' => $super ? 'root' : 'clerk', 'email' => ($super ? 'root' : 'clerk').'@example.com',
            'password' => 'secret-password', 'role' => 'admin', 'is_superuser' => $super,
        ]);
    }

    public function test_command_writes_a_compressed_dump_without_putting_the_password_on_the_command_line(): void
    {
        $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $backups = app(DatabaseBackupService::class)->all();
        $this->assertCount(1, $backups);
        $this->assertMatchesRegularExpression('/-2026-11-17_235900\.sql\.gz$/', $backups[0]['name']);
        $this->assertStringContainsString('CREATE TABLE t', gzdecode(file_get_contents($this->dir.'/'.$backups[0]['name'])));
        $this->assertSame([], glob($this->dir.'/*.tmp'));

        Process::assertRan(fn (PendingProcess $p) => $p->environment['MYSQL_PWD'] === 'pa ss'
            && !collect($p->command)->contains(fn ($a) => str_contains($a, 'pa ss')));
        $this->assertTrue(app(DatabaseBackupService::class)->lastRun()['ok']);
    }

    public function test_failed_or_truncated_dumps_are_discarded_and_reported(): void
    {
        $this->fakeDump(exitCode: 2);
        $this->artisan('backup:database')->assertFailed();

        $this->fakeDump(complete: false);
        $this->artisan('backup:database')->assertFailed();

        $this->assertSame([], app(DatabaseBackupService::class)->all());
        $this->assertSame([], glob($this->dir.'/*.{tmp,partial}', GLOB_BRACE));
        $this->assertFalse(app(DatabaseBackupService::class)->lastRun()['ok']);
    }

    public function test_old_backups_are_pruned_but_the_newest_are_always_kept(): void
    {
        config(['backup.keep_days' => 30, 'backup.keep_min' => 2]);
        File::ensureDirectoryExists($this->dir);
        foreach ([40, 35, 10] as $daysAgo) {
            $file = $this->dir.'/acc263-'.now()->subDays($daysAgo)->format('Y-m-d_His').'.sql.gz';
            file_put_contents($file, 'x');
            touch($file, now()->subDays($daysAgo)->getTimestamp());
        }
        $this->fakeDump();

        $this->artisan('backup:database')->assertSuccessful();

        $names = array_column(app(DatabaseBackupService::class)->all(), 'name');
        $this->assertCount(2, $names);
        $this->assertStringContainsString('2026-11-17', $names[0]);
        $this->assertStringContainsString(now()->subDays(10)->format('Y-m-d'), $names[1]);
    }

    public function test_backup_is_scheduled_daily_at_2359(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'backup:database'));

        $this->assertNotNull($event);
        $this->assertSame('59 23 * * *', $event->expression);
        $this->assertSame('Africa/Harare', $event->timezone);
    }

    public function test_superuser_can_back_up_now_download_and_delete(): void
    {
        $this->fakeDump();
        $admin = $this->superuser();

        $this->actingAs($admin)->get(route('system.backups.index'))->assertOk()->assertSee('No backups yet');
        $this->actingAs($admin)->post(route('system.backups.store'))->assertSessionHas('success');

        $name = app(DatabaseBackupService::class)->all()[0]['name'];
        $this->actingAs($admin)->get(route('system.backups.index'))->assertSee($name);
        $this->actingAs($admin)->get(route('system.backups.download', $name))->assertOk()->assertDownload($name);
        $this->actingAs($admin)->delete(route('system.backups.destroy', $name))->assertSessionHas('success');
        $this->assertSame([], app(DatabaseBackupService::class)->all());
    }

    public function test_non_superusers_are_blocked_and_file_names_cannot_escape_the_backup_folder(): void
    {
        $clerk = $this->superuser(false);
        $this->actingAs($clerk)->get(route('system.backups.index'))->assertForbidden();
        $this->actingAs($clerk)->post(route('system.backups.store'))->assertForbidden();

        $admin = $this->superuser();
        $this->actingAs($admin)->get('/system/backups/..%2F..%2F.env/download')->assertNotFound();
        $this->actingAs($admin)->get(route('system.backups.download', 'acc263-2026-01-01_000000.sql.gz'))->assertNotFound();
    }
}
