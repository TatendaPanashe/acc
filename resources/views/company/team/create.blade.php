@extends('layouts.main')
@section('title', 'Add Team Member')
@section('content')
    @include('accounting.partials.page-header', ['title' => 'Add Team Member', 'section' => 'Company'])
    <div class="card"><div class="body"><form method="POST" action="{{ route('company.team.store') }}">@include('company.team._form')</form></div></div>
@endsection
