<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('accounting.ap.suppliers.index', [
            'suppliers' => Supplier::orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));
        return back()->with('success', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('accounting.ap.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));
        return redirect()->route('accounting.ap.suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['status' => false]);
        return back()->with('success', 'Supplier deactivated.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'supplier_number' => ['required', 'max:40', Rule::unique('accounting_suppliers')->where('company_id', $request->user()->company_id)->ignore($supplier)],
            'name' => ['required', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:80'],
            'remittance_address' => ['nullable', 'max:1000'],
            'currency' => ['nullable', 'size:3'],
            'status' => ['nullable', 'boolean'],
        ]);
    }
}
