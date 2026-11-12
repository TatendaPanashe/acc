@extends('layouts.main')
@section('title', 'Add Company')
@section('content')
<div class="card"><div class="header p-3"><h2>Add Company</h2></div><div class="body p-3"><form method="POST" action="{{ route('system.companies.store') }}" enctype="multipart/form-data">@include('system.companies.form')</form></div></div>
@endsection
