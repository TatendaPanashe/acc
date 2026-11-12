@extends('layouts.platform')

@section('title', $company->name)
@use('App\Support\PlatformFormat', 'F')

@section('styles')
    .tenant-grid { grid-template-columns: minmax(0, 1.4fr) minmax(320px, 1fr); align-items: start; }
    .facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px 24px; margin: 0; }
    .facts dt { color: var(--muted); font-size: 13px; margin-bottom: 2px; }
    .facts dd { margin: 0; font-weight: 600; }
    .actions { display: flex; flex-direction: column; gap: 16px; }
    .action h3 { margin: 0 0 4px; font-size: 15px; display: flex; gap: 8px; align-items: center; }
    .action p { margin: 0 0 12px; color: var(--muted); font-size: 13px; }
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    .timeline { list-style: none; margin: 0; padding: 0; }
    .timeline li { padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 14px; display: flex; justify-content: space-between; gap: 12px; }
    .timeline li:last-child { border-bottom: 0; }
    .timeline .meta { color: var(--muted); font-size: 12px; display: block; }
    .mrr-delta.up { color: var(--good); } .mrr-delta.down { color: var(--bad); }
    .billing td:nth-child(-n+3) { white-space: nowrap; }
    .notice { background: var(--warn-bg); color: var(--warn-text); padding: 10px 14px; border-radius: 10px; font-size: 14px; margin-top: 16px; }
    @media (max-width: 1100px) { .tenant-grid { grid-template-columns: minmax(0, 1fr); } .facts { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@endsection

@section('content')
<div class="page-head">
    <div style="display:flex; align-items:center; gap:14px">
        <span class="avatar" style="width:52px;height:52px;font-size:18px">{{ F::initials($company->name) }}</span>
        <div>
            <h1>{{ $company->name }}</h1>
            <p>{{ config('platform.countries.'.$company->country, $company->country ?: 'Country not set') }} · registered {{ $company->created_at?->format('d M Y') }} · {{ $company->users_count }} {{ str('user')->plural($company->users_count) }}</p>
        </div>
    </div>
    <div style="display:flex; gap:10px; align-items:center">
        <span class="badge {{ $subscription->trialExpired() ? 'expired' : $subscription->status }}">{{ $subscription->statusLabel() }}</span>
        @if($subscription->is_promotional && in_array($subscription->status, ['active', 'past_due']))<span class="badge promo">Promotional</span>@endif
        <a href="{{ route('system.tenants.index') }}" class="btn btn-ghost"><x-platform.icon name="arrow-left" size="16"/> All tenants</a>
    </div>
</div>

<div class="grid tenant-grid">
    <div class="grid">
        <section class="card">
            <div class="card-head"><h2>Subscription</h2></div>
            <dl class="facts">
                <div><dt>Plan</dt><dd>{{ $subscription->plan->name }}</dd></div>
                <div><dt>Billing</dt><dd>{{ ucfirst($subscription->billing_cycle) }} · {{ F::money((float) $subscription->price, true) }}</dd></div>
                <div><dt>MRR</dt><dd>{{ F::money($subscription->mrr(), true) }}</dd></div>
                <div><dt>Payment provider</dt><dd>{{ config('platform.payment_providers.'.$subscription->payment_provider, $subscription->payment_provider ?: '—') }}</dd></div>
                <div><dt>{{ $subscription->status === 'trialing' ? 'Trial ends' : ($subscription->is_promotional ? 'Promotion ends' : 'Current period ends') }}</dt>
                    <dd>{{ ($subscription->status === 'trialing' ? $subscription->trial_ends_at : ($subscription->promo_ends_at ?? $subscription->current_period_ends_at))?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt>Customer since</dt><dd>{{ $subscription->activated_at?->format('d M Y') ?? 'Not yet paying' }}</dd></div>
                <div><dt>Lifetime revenue</dt><dd>{{ F::money($lifetimeRevenue, true) }}</dd></div>
                <div><dt>Contact</dt><dd>{{ $company->email ?: '—' }}</dd></div>
                <div><dt>VAT number</dt><dd>{{ $company->vat_number ?: '—' }}</dd></div>
            </dl>
            @if($subscription->status === 'suspended')
                <div class="notice">Suspended {{ $subscription->suspended_at?->format('d M Y') }}: {{ $subscription->suspension_reason }}. {{ $subscription->suspension_type === 'billing' ? 'Locked to Billing until the open invoice is paid.' : 'Users of this business cannot sign in.' }}</div>
            @elseif($subscription->status === 'cancelled')
                <div class="notice">Cancelled {{ $subscription->cancelled_at?->format('d M Y') }}: {{ $subscription->cancellation_reason }}</div>
            @elseif($subscription->is_promotional)
                <div class="notice">Promotional: {{ $subscription->promo_note }}</div>
            @endif
        </section>

        <section class="card">
            <div class="card-head"><h2>Billing history</h2><span class="sub">{{ $invoices->total() }} {{ str('invoice')->plural($invoices->total()) }}</span></div>
            <div class="table-wrap">
                <table class="data billing">
                    <thead><tr><th>Invoice</th><th>Issued</th><th>Period</th><th>Provider</th><th>Status</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->number }}<span class="muted" style="font-size:12px; display:block">{{ ucfirst($invoice->purpose) }}</span></td>
                            <td>{{ $invoice->issued_at->format('d M Y') }}</td>
                            <td class="muted">{{ $invoice->period_start?->format('d M') }} – {{ $invoice->period_end?->format('d M Y') }}</td>
                            <td>{{ config('platform.payment_providers.'.$invoice->provider, $invoice->provider ?: '—') }}</td>
                            <td><span class="badge {{ $invoice->status === 'pending' ? 'pending' : $invoice->status }}">{{ $invoice->status === 'pending' ? 'Awaiting payment' : ucfirst($invoice->status) }}</span>
                                @foreach($invoice->payments as $payment)
                                    <span class="muted" style="font-size:12px; display:block">{{ $payment->created_at->format('d M') }} · {{ config('platform.payment_providers.'.$payment->provider, $payment->provider) }} · {{ $payment->status }}{{ $payment->failure_reason ? ' ('.$payment->failure_reason.')' : '' }}</span>
                                @endforeach
                                @if($invoice->isOpen())
                                    <details style="margin-top:6px"><summary class="muted" style="font-size:12px; cursor:pointer">Record payment</summary>
                                        <form method="POST" action="{{ route('system.tenants.payments.record', [$company, $invoice]) }}" style="display:grid; gap:6px; margin-top:6px">
                                            @csrf
                                            <select name="provider">@foreach(config('platform.payment_providers') as $key => $label)@continue(in_array($key, ['sandbox', 'coupon']))<option value="{{ $key }}" @selected($key === 'bank_transfer')>{{ $label }}</option>@endforeach</select>
                                            <input type="text" name="reference" required maxlength="120" placeholder="Bank / provider reference">
                                            <button class="btn btn-primary" onclick="return confirm('Record this payment and activate the subscription?')">Record payment</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                            <td class="num">{{ F::money((float) $invoice->amount, true) }} {{ $invoice->currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px">{{ $invoices->links() }}</div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Subscription history</h2></div>
            <ul class="timeline">
                @forelse($events as $event)
                    <li>
                        <span>{{ $event->label() }}
                            @php $d = $event->details ?? []; @endphp
                            <span class="meta">
                                {{ $event->occurred_at->format('d M Y H:i') }}{{ $event->user ? ' · by '.$event->user->name : '' }}
                                @if(isset($d['from']['plan'], $d['to']['plan'])) · {{ ucfirst($d['from']['plan']) }} {{ $d['from']['cycle'] }} → {{ ucfirst($d['to']['plan']) }} {{ $d['to']['cycle'] }}@endif
                                @if(isset($d['days'])) · +{{ $d['days'] }} days to {{ $d['to'] ?? '' }}@endif
                                @if(isset($d['months'])) · {{ ucfirst($d['plan']) }} for {{ $d['months'] }} months @endif
                                @if(!empty($d['reason'])) · {{ $d['reason'] }}@endif
                                @if(!empty($d['note'])) · {{ $d['note'] }}@endif
                            </span>
                        </span>
                        @if((float) $event->mrr_delta != 0)
                            <span class="mrr-delta {{ $event->mrr_delta > 0 ? 'up' : 'down' }} num">{{ $event->mrr_delta > 0 ? '+' : '−' }}{{ F::money(abs((float) $event->mrr_delta), true) }} MRR</span>
                        @endif
                    </li>
                @empty
                    <li class="muted">No changes recorded.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <aside class="actions" aria-label="Subscription actions">
        @if(!in_array($subscription->status, ['suspended', 'cancelled'], true))
            <form method="POST" action="{{ route('system.tenants.plan', $company) }}" class="card action">
                @csrf
                <h3><x-platform.icon name="layers" size="18"/> Change plan</h3>
                <p>Takes effect immediately. Upgrades and downgrades are recorded as MRR expansion or contraction.</p>
                <div class="row-2">
                    <div><label for="plan_id">Plan</label><select id="plan_id" name="platform_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($plan->id === $subscription->platform_plan_id)>{{ $plan->name }} ({{ F::money((float) $plan->monthly_price) }}/mo)</option>@endforeach</select></div>
                    <div><label for="cycle">Billing</label><select id="cycle" name="billing_cycle"><option value="monthly" @selected($subscription->billing_cycle === 'monthly')>Monthly</option><option value="annual" @selected($subscription->billing_cycle === 'annual')>Annual</option></select></div>
                </div>
                <div style="margin-bottom:12px"><label for="plan_note">Note</label><input type="text" id="plan_note" name="note" maxlength="255" placeholder="Optional"></div>
                <details style="margin-bottom:12px"><summary class="muted" style="font-size:13px; cursor:pointer">Custom price and limits (Enterprise deals)</summary>
                    <div class="row-2" style="margin-top:10px">
                        <div><label for="custom_price">Price per billing period ($)</label><input type="number" step="0.01" min="0" id="custom_price" name="custom_price" placeholder="Plan price"></div>
                        <div><label for="limit_users">Users</label><input type="number" min="1" id="limit_users" name="limits[users]" value="{{ $subscription->limit_overrides['users'] ?? '' }}" placeholder="Plan limit"></div>
                        <div><label for="limit_branches">Branches</label><input type="number" min="1" id="limit_branches" name="limits[branches]" value="{{ $subscription->limit_overrides['branches'] ?? '' }}" placeholder="Plan limit"></div>
                        <div><label for="limit_companies">Companies</label><input type="number" min="1" id="limit_companies" name="limits[companies]" value="{{ $subscription->limit_overrides['companies'] ?? '' }}" placeholder="Plan limit"></div>
                    </div>
                </details>
                <button class="btn btn-primary">Change plan</button>
            </form>
        @endif

        <form method="POST" action="{{ route('system.tenants.promotion', $company) }}" class="card action" onsubmit="return confirm('Grant a free promotional subscription? It replaces the current billing.')">
            @csrf
            <h3><x-platform.icon name="gift" size="18"/> Grant promotional subscription</h3>
            <p>Free access to a plan for a set number of months. Adds no MRR while it lasts.</p>
            <div class="row-2">
                <div><label for="promo_plan">Plan</label><select id="promo_plan" name="platform_plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($plan->id === $subscription->platform_plan_id)>{{ $plan->name }}</option>@endforeach</select></div>
                <div><label for="months">Months</label><input type="number" id="months" name="months" min="1" max="36" value="3" required></div>
            </div>
            <div style="margin-bottom:12px"><label for="promo_note">Reason</label><input type="text" id="promo_note" name="note" maxlength="255" required placeholder="e.g. Launch partner offer"></div>
            <button class="btn btn-outline">Grant promotion</button>
        </form>

        @if(in_array($subscription->status, ['trialing', 'expired'], true))
            <form method="POST" action="{{ route('system.tenants.trial', $company) }}" class="card action">
                @csrf
                <h3><x-platform.icon name="clock" size="18"/> Extend trial</h3>
                <p>Currently {{ $subscription->trialExpired() || $subscription->status === 'expired' ? 'ended on' : 'ends' }} {{ $subscription->trial_ends_at?->format('d M Y') }}. An expired trial is extended from today.</p>
                <div class="row-2">
                    <div><label for="days">Extra days</label><input type="number" id="days" name="days" min="1" max="180" value="14" required></div>
                    <div><label for="trial_note">Note</label><input type="text" id="trial_note" name="note" maxlength="255" placeholder="Optional"></div>
                </div>
                <button class="btn btn-outline">Extend trial</button>
            </form>
        @endif

        @if($subscription->status === 'suspended')
            <form method="POST" action="{{ route('system.tenants.reactivate', $company) }}" class="card action">
                @csrf
                <h3><x-platform.icon name="check" size="18"/> Reactivate account</h3>
                <p>Restores sign-in for this business and returns it to {{ $subscription->status_before_suspension ? str_replace('_', ' ', $subscription->status_before_suspension) : 'active' }}.</p>
                <div style="margin-bottom:12px"><label for="reactivate_note">Note</label><input type="text" id="reactivate_note" name="note" maxlength="255" placeholder="Optional"></div>
                <button class="btn btn-primary">Reactivate</button>
            </form>
        @else
            <form method="POST" action="{{ route('system.tenants.suspend', $company) }}" class="card action" onsubmit="return confirm('Suspend this business? Its users will be signed out and blocked.')">
                @csrf
                <h3><x-platform.icon name="pause" size="18"/> Suspend account</h3>
                <p>Blocks sign-in for every user of this business and removes its MRR until reactivated.</p>
                <div style="margin-bottom:12px"><label for="suspend_reason">Reason</label><input type="text" id="suspend_reason" name="reason" maxlength="255" required placeholder="e.g. Repeated failed payments"></div>
                <button class="btn btn-danger">Suspend</button>
            </form>
        @endif

        @if(!in_array($subscription->status, ['cancelled', 'suspended'], true))
            <form method="POST" action="{{ route('system.tenants.cancel', $company) }}" class="card action" onsubmit="return confirm('Cancel this subscription?')">
                @csrf
                <h3><x-platform.icon name="x-circle" size="18"/> Cancel subscription</h3>
                <p>Records a cancellation (for example at the customer's request). Users can still sign in.</p>
                <div style="margin-bottom:12px"><label for="cancel_reason">Reason</label><input type="text" id="cancel_reason" name="reason" maxlength="255" required placeholder="e.g. Customer closed the business"></div>
                <button class="btn btn-danger">Cancel subscription</button>
            </form>
        @endif
    </aside>
</div>
@endsection
