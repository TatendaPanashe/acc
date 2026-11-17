<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(): View
    {
        $all = $this->backups->all();

        return view('platform.backups', [
            'backups' => $all,
            'totalSize' => array_sum(array_column($all, 'size')),
            'lastRun' => $this->backups->lastRun(),
            'directory' => $this->backups->directory(),
        ]);
    }

    public function store(): RedirectResponse
    {
        @set_time_limit((int) config('backup.timeout') + 60);

        try {
            $backup = $this->backups->run('manual:'.auth()->user()->username);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Backup failed: '.$e->getMessage());
        }

        return back()->with('success', 'Backup '.$backup['name'].' created.');
    }

    public function download(string $name): BinaryFileResponse
    {
        $path = $this->backups->pathFor($name) ?? abort(404);
        Log::info('Database backup downloaded', ['file' => $name, 'user' => auth()->id()]);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $name): RedirectResponse
    {
        abort_unless($this->backups->delete($name), 404);
        Log::info('Database backup deleted', ['file' => $name, 'user' => auth()->id()]);

        return back()->with('success', 'Backup '.$name.' deleted.');
    }
}
