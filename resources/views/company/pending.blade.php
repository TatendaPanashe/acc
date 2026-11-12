@extends('layouts.main')

@section('title', 'Workspace Not Ready')

@section('content')
    @include('accounting.partials.page-header', ['title' => 'Workspace Not Ready', 'section' => 'Company Setup'])
    <div class="card">
        <div class="body">
            <h5>{{ auth()->user()->company?->name ?? 'Your company' }} is still being set up.</h5>
            <p class="mb-3">Your company administrator must finish setup (profile, currencies, tax and the chart of accounts) before you can start work. Please try again later.</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-secondary">Log out</button></form>
        </div>
    </div>
@endsection
