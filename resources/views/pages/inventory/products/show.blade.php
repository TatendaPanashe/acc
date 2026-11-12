@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')
 <div class="container">
        <h1>Product Details</h1>
        <div class="card">
            <div class="card-body">
                <p><strong>Name:</strong> {{ $product->name }}</p>
                <p><strong>SKU:</strong> {{ $product->sku }}</p>
                <p><strong>Description:</strong> {{ $product->description ?? 'N/A' }}</p>
                <p><strong>Price:</strong> ${{ number_format($product->price, 2) }}</p>
                <p><strong>Cost:</strong> ${{ number_format($product->cost, 2) }}</p>
                <p><strong>Stock Quantity:</strong> {{ $product->stock_quantity }}</p>
                <p><strong>Category:</strong> {{ $product->category->name ?? 'N/A' }}</p>
                <p><strong>Supplier:</strong> {{ $product->supplier->name ?? 'N/A' }}</p>
                <p><strong>Status:</strong> <span class="badge {{ $product->status == 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($product->status) }}</span></p>
            </div>
        </div>
        <a href="{{ route('products.index') }}" class="btn btn-secondary mt-3">Back</a>
    </div>
@endsection
