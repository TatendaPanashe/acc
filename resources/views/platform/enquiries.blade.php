@extends('layouts.platform')
@section('title', 'Enquiries')
@section('styles')
    .enquiry { border-bottom: 1px solid var(--border); padding: 16px 0; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 16px; }
    .enquiry:last-child { border-bottom: 0; }
    .enquiry p { margin: 8px 0 0; white-space: pre-line; }
@endsection
@section('content')
<div class="page-head">
    <div><h1>Enquiries</h1><p>Messages from the website contact form · {{ $open }} open</p></div>
    <a class="btn btn-ghost" href="{{ route('system.enquiries.index', request('show') === 'all' ? [] : ['show' => 'all']) }}">{{ request('show') === 'all' ? 'Show open only' : 'Show all' }}</a>
</div>
<div class="card">
    @forelse($enquiries as $enquiry)
        <div class="enquiry">
            <div>
                <strong>{{ $enquiry->name }}</strong> · <a href="mailto:{{ $enquiry->email }}?subject={{ rawurlencode('Re: '.$enquiry->topicLabel()) }}">{{ $enquiry->email }}</a>
                @if($enquiry->company) · {{ $enquiry->company }}@endif @if($enquiry->phone) · {{ $enquiry->phone }}@endif
                <span class="muted" style="display:block;font-size:13px">{{ $enquiry->topicLabel() }} · {{ $enquiry->created_at->format('d M Y H:i') }}</span>
                <p>{{ $enquiry->message }}</p>
            </div>
            <form method="POST" action="{{ route('system.enquiries.handled', $enquiry) }}">@csrf<button class="btn {{ $enquiry->handled_at ? 'btn-ghost' : 'btn-primary' }}">{{ $enquiry->handled_at ? 'Reopen' : 'Mark handled' }}</button></form>
        </div>
    @empty
        <p class="muted" style="margin:0">No {{ request('show') === 'all' ? '' : 'open ' }}enquiries.</p>
    @endforelse
    <div style="margin-top:12px">{{ $enquiries->links() }}</div>
</div>
@endsection
