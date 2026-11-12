@extends('layouts.main')
@section('title', 'Edit User')
@section('content')
<div class="card"><div class="header p-3"><h2>Edit User</h2></div><div class="body p-3"><form method="POST" action="{{ route('system.users.update', $user) }}">@include('system.users.form')</form></div></div>
@endsection
