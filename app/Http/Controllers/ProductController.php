<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier; // Optional
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index() {
        $products = Product::with(['category', 'supplier'])->latest()->paginate(10);
        return view('pages.inventory.products.index', compact('products'));
    }

    public function create() {
        $categories = Category::all();
        $suppliers = Supplier::all(); // Optional
        return view('pages.inventory.products.create', compact('categories', 'suppliers'));
    }

    public function store(Request $request) {
        $request->validate([
            'name'           => 'required|string|max:255',
            'sku'            => 'required|string|unique:products|max:100',
            'description'    => 'nullable|string|max:1000',
            'price'          => 'required|numeric|min:0',
            'cost'           => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'supplier_id'    => 'nullable|exists:suppliers,id',
            'status'         => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);
        try { DB::beginTransaction(); Product::create($request->all()); DB::commit(); return redirect()->route('products.index')->with('success', 'Product created.'); }
        catch (\Exception $e) { DB::rollback(); return back()->withInput()->with('error', $e->getMessage()); }
    }

    public function show(Product $product) {
        $product->load(['category', 'supplier']);
        return view('pages.inventory.products.show', compact('product'));
    }

    public function edit(Product $product) {
        $categories = Category::all();
        $suppliers = Supplier::all();
        return view('pages.inventory.products.edit', compact('product', 'categories', 'suppliers'));
    }

    public function update(Request $request, Product $product) {
        $request->validate([
            'name'           => 'required|string|max:255',
            'sku'            => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'description'    => 'nullable|string|max:1000',
            'price'          => 'required|numeric|min:0',
            'cost'           => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'supplier_id'    => 'nullable|exists:suppliers,id',
            'status'         => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);
        try { DB::beginTransaction(); $product->update($request->all()); DB::commit(); return redirect()->route('pages.inventory.products.index')->with('success', 'Product updated.'); }
        catch (\Exception $e) { DB::rollback(); return back()->withInput()->with('error', $e->getMessage()); }
    }

    public function destroy(Product $product) {
        try { DB::beginTransaction(); $product->delete(); DB::commit(); return redirect()->route('pages.inventory.products.index')->with('success', 'Product deleted.'); }
        catch (\Exception $e) { DB::rollback(); return back()->with('error', 'Failed to delete: ' . $e->getMessage()); }
    }
}
