@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')

  <div class="container">
        <h1>Category Details</h1>
        <div class="card">
            <div class="card-body">
                <p><strong>Name:</strong> {{ $category->name }}</p>
                <p><strong>Description:</strong> {{ $category->description ?? 'N/A' }}</p>
            </div>
        </div>
        <a href="{{ route('categories.index') }}" class="btn btn-secondary mt-3">Back</a>
    </div>
@endsection
