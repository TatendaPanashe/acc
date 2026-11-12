@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')


<div class="container">
        <h1>Products</h1>

        <div class="mb-3">
            <a href="{{ route('products.create') }}" class="btn btn-primary">Add New Product</a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->sku }}</td>
                                <td>${{ number_format($product->price, 2) }}</td>
                                <td>{{ $product->stock_quantity }}</td>
                                <td>{{ $product->category->name ?? 'N/A' }}</td>
                                <td><span class="badge {{ $product->status == 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
                                <td>
                                    <a href="{{ route('products.show', $product->id) }}" class="btn btn-sm btn-info">View</a>
                                    <a href="{{ route('products.edit', $product->id) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    <form action="{{ route('products.destroy', $product->id) }}" method="POST" style="display: inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $products->links() }}
            </div>
        </div>
    </div>
@endsection
