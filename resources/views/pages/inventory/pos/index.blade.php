@extends('layouts.main')

@section('title')
Welcome
@endsection

@section('content')
<div class="container-fluid">
        <h1>Point of Sale</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">Products</div>
                    <div class="card-body">
                        <div class="input-group mb-3">
                            <input type="text" id="product-search" class="form-control" placeholder="Search product by name or SKU...">
                        </div>
                        <div class="product-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 10px;">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="products-table-body">
                                    @foreach($products as $product)
                                        <tr data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $product->price }}" data-product-stock="{{ $product->stock_quantity }}">
                                            <td>{{ $product->name }}</td>
                                            <td>{{ $product->sku }}</td>
                                            <td>${{ number_format($product->price, 2) }}</td>
                                            <td>{{ $product->stock_quantity }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary add-to-cart-btn" data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $product->price }}" data-product-stock="{{ $product->stock_quantity }}">Add</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">Cart</div>
                    <div class="card-body">
                        <form id="pos-form" action="{{ route('sales.process') }}" method="POST">
                            @csrf
                            <table class="table table-sm" id="cart-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cart-items-body">
                                    {{-- Cart items will be added here by JavaScript --}}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">Total:</th>
                                        <th id="cart-total">$0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>

                            <input type="hidden" name="total_amount" id="form-total-amount">
                            <input type="hidden" name="cart_items" id="form-cart-items">

                            <div class="form-group mb-3">
                                <label for="customer_id">Customer (Optional):</label>
                                <select name="customer_id" id="customer_id" class="form-control">
                                    <option value="">Walk-in Customer</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label for="payment_method">Payment Method:</label>
                                <select name="payment_method" id="payment_method" class="form-control" required>
                                    <option value="">-- Select Method --</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label for="paid_amount">Amount Paid:</label>
                                <input type="number" name="paid_amount" id="paid_amount" class="form-control" step="0.01" min="0" required>
                            </div>

                            <div class="form-group mb-3">
                                <label>Change:</label>
                                <p id="change-amount">$0.00</p>
                            </div>

                            <button type="submit" class="btn btn-success btn-block">Complete Sale</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    let cart = {};
    const products = @json($products);
    function updateCartDisplay() {
        const cartItemsBody = document.getElementById('cart-items-body');
        const cartTotalDisplay = document.getElementById('cart-total');
        const formTotalAmount = document.getElementById('form-total-amount');
        const formCartItems = document.getElementById('form-cart-items');
        const paidAmountInput = document.getElementById('paid_amount');
        const changeAmountDisplay = document.getElementById('change-amount');

        cartItemsBody.innerHTML = '';
        let total = 0;

        for (const productId in cart) {
            const item = cart[productId];
            const row = `
                <tr>
                    <td>${item.name}</td>
                    <td><input type="number" class="form-control form-control-sm cart-qty-input" data-product-id="${item.id}" value="${item.quantity}" min="1" max="${item.stock}"></td>
                    <td>$${item.price.toFixed(2)}</td>
                    <td>$${item.subtotal.toFixed(2)}</td>
                    <td><button type="button" class="btn btn-sm btn-danger remove-from-cart-btn" data-product-id="${item.id}">X</button></td>
                </tr>
            `;
            cartItemsBody.innerHTML += row;
            total += item.subtotal;
        }

        cartTotalDisplay.innerText = `$${total.toFixed(2)}`;
        formTotalAmount.value = total.toFixed(2);
        formCartItems.value = JSON.stringify(Object.values(cart).map(item => ({ product_id: item.id, quantity: item.quantity })));

        // Update change
        const paidAmount = parseFloat(paidAmountInput.value) || 0;
        const change = paidAmount - total;
        changeAmountDisplay.innerText = `$${change.toFixed(2)}`;
    }

    function addToCart(product) {
        if (cart[product.id]) {
            if (cart[product.id].quantity < product.stock_quantity) {
                cart[product.id].quantity++;
            } else {
                alert('Cannot add more than available stock.');
                return;
            }
        } else {
            cart[product.id] = {
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                stock: product.stock_quantity,
                quantity: 1,
                subtotal: parseFloat(product.price)
            };
        }
        cart[product.id].subtotal = cart[product.id].quantity * cart[product.id].price;
        updateCartDisplay();
    }

    function removeFromCart(productId) {
        delete cart[productId];
        updateCartDisplay();
    }

    function updateCartQuantity(productId, newQuantity) {
        if (cart[productId]) {
            const product = products.find(p => p.id === productId); // Find original product to check stock
            if (newQuantity > 0 && newQuantity <= product.stock_quantity) {
                cart[productId].quantity = newQuantity;
                cart[productId].subtotal = cart[productId].quantity * cart[productId].price;
            } else if (newQuantity <= 0) {
                removeFromCart(productId);
            } else {
                alert('Cannot exceed available stock for this product.');
                cart[productId].quantity = product.stock_quantity; // Reset to max allowed
            }
        }
        updateCartDisplay();
    }

    document.getElementById('products-table-body').addEventListener('click', function(event) {
        if (event.target.classList.contains('add-to-cart-btn')) {
            const productId = parseInt(event.target.dataset.productId);
            const product = products.find(p => p.id === productId);
            if (product) {
                addToCart(product);
            }
        }
    });

    document.getElementById('cart-items-body').addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-from-cart-btn')) {
            const productId = parseInt(event.target.dataset.productId);
            removeFromCart(productId);
        }
    });

    document.getElementById('cart-items-body').addEventListener('change', function(event) {
        if (event.target.classList.contains('cart-qty-input')) {
            const productId = parseInt(event.target.dataset.productId);
            const newQuantity = parseInt(event.target.value);
            updateCartQuantity(productId, newQuantity);
        }
    });

    document.getElementById('paid_amount').addEventListener('input', updateCartDisplay);

    // Product search functionality
    document.getElementById('product-search').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const filteredProducts = products.filter(product =>
            product.name.toLowerCase().includes(searchTerm) ||
            product.sku.toLowerCase().includes(searchTerm)
        );
        const productsTableBody = document.getElementById('products-table-body');
        productsTableBody.innerHTML = '';
        filteredProducts.forEach(product => {
            const row = `
                <tr data-product-id="${product.id}" data-product-name="${product.name}" data-product-price="${product.price}" data-product-stock="${product.stock_quantity}">
                    <td>${product.name}</td>
                    <td>${product.sku}</td>
                    <td>$${product.price.toFixed(2)}</td>
                    <td>${product.stock_quantity}</td>
                    <td>
                        <button class="btn btn-sm btn-primary add-to-cart-btn" data-product-id="${product.id}" data-product-name="${product.name}" data-product-price="${product.price}" data-product-stock="${product.stock_quantity}">Add</button>
                    </td>
                </tr>
            `;
            productsTableBody.innerHTML += row;
        });
    });

    // Initialize cart display on page load
    updateCartDisplay();
</script>
@endpush
