@extends('layouts.platform')
@section('title', 'Backups')
@php
    $kb = fn (int $bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format($bytes / 1024, 1).' KB';
@endphp
@section('styles')
    .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat strong { display: block; font-size: 22px; }
    .stat span { color: var(--muted); font-size: 13px; }
    .row-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .row-actions form { margin: 0; }
    @media (max-width: 760px) { .stats { grid-template-columns: minmax(0, 1fr); } }
@endsection
@section('content')
<div class="page-head">
    <div><h1>Backups</h1><p>The database is backed up automatically every day at {{ config('backup.daily_at') }}. Back up now before risky changes, and download copies to keep somewhere safe.</p></div>
    <form method="POST" action="{{ route('system.backups.store') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').lastChild.textContent = ' Backing up…';">
        @csrf
        <button class="btn btn-primary"><x-platform.icon name="shield" size="16"/> Back up now</button>
    </form>
</div>

@if($lastRun && !$lastRun['ok'])
    <div class="flash error" role="alert"><x-platform.icon name="alert" size="18"/>
        Last backup ({{ \Illuminate\Support\Carbon::parse($lastRun['at'])->format('d M Y H:i') }}) failed: {{ $lastRun['error'] }}
    </div>
@endif

<div class="stats">
    <div class="card stat">
        <strong>{{ $backups ? $backups[0]['created_at']->diffForHumans() : 'Never' }}</strong>
        <span>Latest backup{{ $backups ? ' · '.$backups[0]['created_at']->format('d M Y H:i') : '' }}</span>
    </div>
    <div class="card stat"><strong>{{ count($backups) }}</strong><span>Backups kept · older than {{ config('backup.keep_days') }} days are removed</span></div>
    <div class="card stat"><strong>{{ $kb($totalSize) }}</strong><span>Total size{{ config('backup.copy_to_disk') ? ' · also copied to '.config('backup.copy_to_disk') : '' }}</span></div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>File</th><th>Created</th><th class="num">Size</th><th></th></tr></thead>
            <tbody>
            @forelse($backups as $backup)
                <tr>
                    <td><strong>{{ $backup['name'] }}</strong></td>
                    <td>{{ $backup['created_at']->format('d M Y H:i') }}<br><span class="muted" style="font-size:12px">{{ $backup['created_at']->diffForHumans() }}</span></td>
                    <td class="num">{{ $kb($backup['size']) }}</td>
                    <td><div class="row-actions">
                        <a class="btn btn-ghost" href="{{ route('system.backups.download', $backup['name']) }}"><x-platform.icon name="arrow-down" size="16"/> Download</a>
                        <form method="POST" action="{{ route('system.backups.destroy', $backup['name']) }}" onsubmit="return confirm('Delete {{ $backup['name'] }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger">Delete</button>
                        </form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No backups yet. Click “Back up now” to create the first one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="muted" style="font-size:13px;margin:16px 0 0">Stored in <code>{{ $directory }}</code>. To restore: <code>gunzip &lt; FILE.sql.gz | mysql -u USER -p DATABASE</code></p>
</div>
@endsection
