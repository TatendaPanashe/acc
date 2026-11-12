<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.warehouses.index', [
            'warehouses' => Warehouse::orderBy('code')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        app(\App\Services\Billing\Entitlements::class)->assertWithinLimit($request->user()->company, 'branches', 1, 'code');
        Warehouse::create($data);

        return back()->with('success', 'Warehouse created.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('accounting.inventory.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));

        return redirect()->route('accounting.inventory.warehouses.index')->with('success', 'Warehouse updated.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update(['status' => false]);

        return back()->with('success', 'Warehouse deactivated.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'code' => ['required', 'max:40', Rule::unique('accounting_warehouses')->where('company_id', $request->user()->company_id)->ignore($warehouse)],
            'name' => ['required', 'max:255'],
            'location' => ['nullable', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]) + ['status' => true];
    }
}
