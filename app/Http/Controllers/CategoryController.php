<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index() {
        $categories = Category::latest()->paginate(10);
        return view('pages.inventory.categories.index', compact('categories'));
    }

    public function create() {
        return view('pages.inventory.categories.create');
    }

    public function store(Request $request) {
        $request->validate(['name' => 'required|unique:categories|max:255']);
        try { DB::beginTransaction(); Category::create($request->all()); DB::commit(); return redirect()->route('categories.index')->with('success', 'Category created.'); }
        catch (\Exception $e) { DB::rollback(); return back()->withInput()->with('error', $e->getMessage()); }
    }

    public function show(Category $category) {
        return view('pages.inventory.categories.show', compact('category'));
    }

    public function edit(Category $category) {
        return view('pages.inventory.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category) {
        $request->validate(['name' => 'required|unique:categories,name,'.$category->id.'|max:255']);
        try { DB::beginTransaction(); $category->update($request->all()); DB::commit(); return redirect()->route('pages.inventory.categories.index')->with('success', 'Category updated.'); }
        catch (\Exception $e) { DB::rollback(); return back()->withInput()->with('error', $e->getMessage()); }
    }

    public function destroy(Category $category) {
        try { DB::beginTransaction(); $category->delete(); DB::commit(); return redirect()->route('pages.inventory.categories.index')->with('success', 'Category deleted.'); }
        catch (\Exception $e) { DB::rollback(); return back()->with('error', 'Failed to delete: ' . $e->getMessage()); }
    }
}
