@extends('layouts.platform')
@section('title', 'Payment gateways')
@section('styles')
    .gw-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 12px 0; }
    .hint { font-size: 12px; color: var(--muted); word-break: break-all; }
    @media (max-width: 1100px) { .gw-grid, .fields { grid-template-columns: minmax(0, 1fr); } }
@endsection
@section('content')
<div class="page-head"><div><h1>Payment gateways</h1><p>Credentials are stored encrypted and never shown again; leave a secret blank to keep it.</p></div></div>
<div class="grid gw-grid">
    @foreach($gateways as $gateway)
        <form method="POST" action="{{ route('system.gateways.update', $gateway) }}" class="card">
            @csrf @method('PUT')
            <div class="card-head"><h2>{{ ucfirst(str_replace('_', ' ', $gateway->provider)) }}</h2>
                <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="status" value="1" @checked($gateway->status)> Enabled</label></div>
            <div class="fields">
                <div><label>Name shown to customers</label><input type="text" name="display_name" value="{{ $gateway->display_name }}" required></div>
                <div><label>Mode</label><select name="mode"><option value="test" @selected($gateway->mode === 'test')>Test</option><option value="live" @selected($gateway->mode === 'live')>Live</option></select></div>
                @foreach($fields[$gateway->provider] ?? [] as $key => $label)
                    @php [$text, $secret] = array_pad(explode('|', $label), 2, null); @endphp
                    <div><label>{{ $text }}</label>
                        @if($secret)
                            <input type="password" name="config[{{ $key }}]" autocomplete="new-password" placeholder="{{ $gateway->config($key) ? '•••••••• (saved)' : 'Not set' }}">
                        @else
                            <input type="text" name="config[{{ $key }}]" value="{{ $gateway->config($key) }}">
                        @endif
                    </div>
                @endforeach
            </div>
            @if(in_array($gateway->provider, ['paynow', 'stripe']))
                <p class="hint">Webhook / result URL: {{ route('billing.webhooks', $gateway->provider) }}</p>
            @elseif($gateway->provider === 'sandbox')
                <p class="hint">Lets testers simulate successful and failed payments. Cannot be enabled in production.</p>
            @elseif($gateway->provider === 'bank_transfer')
                <p class="hint">Customers get these details and a reference; record the payment on the tenant's page when it arrives.</p>
            @endif
            <button class="btn btn-primary">Save</button>
        </form>
    @endforeach
</div>
<div class="card" style="margin-top:20px">
    <div class="card-head"><h2>Recent webhooks</h2></div>
    <div class="table-wrap"><table class="data">
        <thead><tr><th>Received</th><th>Provider</th><th>Event</th><th>Signature</th><th>Result</th></tr></thead>
        <tbody>
        @forelse($webhooks as $hook)
            <tr><td>{{ $hook->created_at->format('d M Y H:i') }}</td><td>{{ $hook->provider }}</td><td class="hint">{{ $hook->event_id }}</td>
                <td><span class="badge {{ $hook->signature_valid ? 'paid' : 'failed' }}">{{ $hook->signature_valid ? 'Valid' : 'Invalid' }}</span></td>
                <td>{{ $hook->error ?? ($hook->processed_at ? 'Processed' : 'Pending') }}</td></tr>
        @empty
            <tr><td colspan="5" class="muted">No webhooks received yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
