@extends('layouts.main')
@section('title', 'Edit Team Member')
@section('content')
    @include('accounting.partials.page-header', ['title' => 'Edit Team Member', 'section' => 'Company'])
    <div class="card"><div class="body"><form method="POST" action="{{ route('company.team.update', $member) }}">@include('company.team._form')</form></div></div>
@endsection
