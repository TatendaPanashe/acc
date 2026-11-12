@extends('layouts.main')
@section('title', 'Subscription inactive')
@section('content')
@include('accounting.partials.page-header', ['title' => 'Subscription inactive', 'section' => 'Billing'])
<div class="card"><div class="body">
    <h5>{{ auth()->user()->company?->name }} cannot be used right now.</h5>
    <p>{{ $access['message'] }}</p>
    <p class="mb-3">Your Company Admin can restore access from <strong>Billing &amp; Plan</strong>. Nothing has been deleted.</p>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Log out</button></form>
</div></div>
@endsection
