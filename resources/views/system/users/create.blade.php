@extends('layouts.main')
@section('title', 'Add User')
@section('content')
<div class="card"><div class="header p-3"><h2>Add User</h2></div><div class="body p-3"><form method="POST" action="{{ route('system.users.store') }}">@include('system.users.form')</form></div></div>
@endsection
