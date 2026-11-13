@extends('staff.layout')

@section('title', 'Leave')

@section('content')
    <h1>Leave</h1>
    <p class="muted">Days are counted Monday to Friday. Requests go to your payroll office for approval.</p>
    <div class="card">
        <h2>Request leave</h2>
        <form method="POST" action="{{ route('staff.leave.store') }}">
            @csrf
            <div class="fields">
                <div><label for="leave_type_id">Type</label><select id="leave_type_id" name="leave_type_id" required>@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
                <div><label for="starts_on">First day</label><input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on') }}" required></div>
                <div><label for="ends_on">Last day</label><input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on') }}" required></div>
            </div>
            <div style="margin-bottom:12px"><label for="reason">Reason or note (optional)</label><textarea id="reason" name="reason" rows="2" maxlength="1000">{{ old('reason') }}</textarea></div>
            <button class="btn btn-primary">Send request</button>
        </form>
    </div>

    <h2>Balances {{ now()->year }}</h2>
    @include('staff._balances', ['balances' => $balances])
    <div style="height:16px"></div>

    <div class="card">
        <h2>My leave</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Type</th><th>Dates</th><th class="num">Days</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($leave as $item)
                    <tr>
                        <td>{{ $item->type?->name }}@if($item->reason)<div class="small muted">{{ $item->reason }}</div>@endif</td>
                        <td>{{ $item->starts_on->format('d M') }} – {{ $item->ends_on->format('d M Y') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->days, 2), '0'), '.') }}</td>
                        <td><span class="badge {{ $item->status }}">{{ \App\Modules\Payroll\Models\LeaveRequest::STATUSES[$item->status] }}</span>@if($item->decision_note)<div class="small muted">{{ $item->decision_note }}</div>@endif</td>
                        <td class="num">@if($item->status === 'pending')<form method="POST" action="{{ route('staff.leave.cancel', $item->id) }}" style="margin:0" onsubmit="return confirm('Cancel this request?')">@csrf<button class="btn btn-link">Cancel</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No leave requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
