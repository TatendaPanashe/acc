@extends('layouts.main')
@section('title', 'Edit Company')
@section('content')
<div class="card"><div class="header p-3"><h2>Edit Company</h2></div><div class="body p-3"><form method="POST" action="{{ route('system.companies.update', $company) }}" enctype="multipart/form-data">@include('system.companies.form')</form></div></div>
@endsection
