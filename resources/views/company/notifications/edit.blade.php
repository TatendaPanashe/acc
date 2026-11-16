@extends('layouts.main')
@section('title', 'Notifications')
@section('content')
    @include('accounting.partials.page-header', ['title' => 'Notifications', 'section' => 'Company'])

    @if($mailer === 'log' || $mailer === 'array')
        <div class="alert alert-warning">
            Email delivery is not configured yet (<code>MAIL_MAILER={{ $mailer }}</code>): emails are written to the log instead of being sent.
            Set the <code>MAIL_*</code> settings in <code>.env</code> to start delivering them.
        </div>
    @endif

    <form method="POST" action="{{ route('company.notifications.update') }}">
        @csrf @method('PUT')
        @foreach($groups as $group => $groupLabel)
            @continue(!isset($events[$group]))
            <div class="card">
                <div class="header"><h2><strong>{{ $groupLabel }}</strong></h2></div>
                <div class="body">
                    @foreach($events[$group] as $event)
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="ev-{{ $loop->parent->index }}-{{ $loop->index }}"
                                   name="events[{{ $event['key'] }}]" value="1" @checked($event['enabled']) @disabled($event['locked'])>
                            <label class="form-check-label" for="ev-{{ $loop->parent->index }}-{{ $loop->index }}">
                                <strong>{{ $event['label'] }}</strong>
                                @if($event['locked'])<span class="badge bg-secondary ms-1">always on</span>@endif
                                <br><small class="text-muted">{{ $event['description'] }}</small>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="d-flex gap-2 mb-4">
            <button class="btn btn-primary"><i class="fas fa-save"></i> Save settings</button>
        </div>
    </form>

    <div class="card">
        <div class="header d-flex justify-content-between align-items-center">
            <h2><strong>Recent emails</strong></h2>
            <form method="POST" action="{{ route('company.notifications.test') }}">
                @csrf<button class="btn btn-outline-primary btn-sm"><i class="fas fa-paper-plane"></i> Send me a test email</button>
            </form>
        </div>
        <div class="body table-responsive">
            @if($recent->isEmpty())
                <p class="text-muted mb-0">No emails sent yet.</p>
            @else
                <table class="table table-striped">
                    <thead><tr><th>When</th><th>Email</th><th>To</th></tr></thead>
                    <tbody>
                    @foreach($recent as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                            <td>{{ config('notifications.events')[$row->event][0] ?? $row->event }}</td>
                            <td>{{ $row->recipient }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
