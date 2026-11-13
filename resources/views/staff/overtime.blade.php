@extends('staff.layout')

@section('title', 'Overtime')

@section('content')
    <h1>Overtime</h1>
    <p class="muted">Claim overtime you have already worked (within the last 90 days). Approved hours are paid in the payroll for the month you worked them.</p>

    <div class="card">
        <h2>Claim overtime</h2>
        <form method="POST" action="{{ route('staff.overtime.store') }}">
            @csrf
            <div class="fields">
                <div><label for="work_date">Date worked</label><input id="work_date" type="date" name="work_date" value="{{ old('work_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
                <div><label for="hours">Hours</label><input id="hours" type="number" step="0.25" min="0.25" max="24" name="hours" value="{{ old('hours') }}" required></div>
            </div>
            <div style="margin-bottom:12px"><label for="reason">What was the overtime for?</label><textarea id="reason" name="reason" rows="2" maxlength="1000" required>{{ old('reason') }}</textarea></div>
            <button class="btn btn-primary">Send claim</button>
        </form>
    </div>

    <div class="card">
        <h2>My claims</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th class="num">Hours</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($overtime as $item)
                    <tr>
                        <td>{{ $item->work_date->format('d M Y') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->hours, 2), '0'), '.') }}</td>
                        <td>{{ $item->reason }}</td>
                        <td><span class="badge {{ $item->status }}">{{ \App\Modules\Payroll\Models\OvertimeRequest::STATUSES[$item->status] }}</span>
                            @if($item->run)<div class="small muted">Paid in {{ $item->run->period_end->format('F Y') }} payroll</div>@elseif($item->status === 'approved')<div class="small muted">To be paid in the {{ $item->work_date->format('F Y') }} payroll</div>@endif
                            @if($item->decision_note)<div class="small muted">{{ $item->decision_note }}</div>@endif</td>
                        <td class="num">@if($item->status === 'pending')<form method="POST" action="{{ route('staff.overtime.cancel', $item->id) }}" style="margin:0" onsubmit="return confirm('Cancel this claim?')">@csrf<button class="btn btn-link">Cancel</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No overtime claims yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
