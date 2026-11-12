<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\StockMovement;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.items.index', [
            'items' => Item::orderBy('sku')->paginate(25),
            'onHand' => StockMovement::selectRaw('item_id, SUM(quantity) as qty')->groupBy('item_id')->pluck('qty', 'item_id'),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, InventoryStockService $stock, InventoryPostingService $ledger): RedirectResponse
    {
        $data = $this->validated($request);
        $opening = $this->openingStock($request, $data['type']);

        $item = DB::transaction(function () use ($data, $opening, $stock, $ledger) {
            $item = Item::create($data);
            $this->receiveOpeningStock($item, $opening, $stock, $ledger);

            return $item;
        });

        return back()->with('success', $this->message('Item '.$item->sku.' created', $opening));
    }

    public function edit(Item $item): View
    {
        return view('accounting.inventory.items.edit', [
            'item' => $item,
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'canAddOpeningStock' => $item->type === 'stock' && !$this->hasMovements($item),
        ]);
    }

    public function update(Request $request, Item $item, InventoryStockService $stock, InventoryPostingService $ledger): RedirectResponse
    {
        $data = $this->validated($request, $item);
        $opening = $this->openingStock($request, $data['type']);

        if ($opening['lines'] !== [] && $this->hasMovements($item)) {
            throw ValidationException::withMessages(['opening' => 'This item already has stock movements. Use a purchase order to bring in more stock.']);
        }

        DB::transaction(function () use ($item, $data, $opening, $stock, $ledger) {
            $item->update($data);
            $this->receiveOpeningStock($item, $opening, $stock, $ledger);
        });

        return redirect()->route('accounting.inventory.items.index')->with('success', $this->message('Item updated', $opening));
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->update(['status' => false]);

        return back()->with('success', 'Item deactivated.');
    }

    /**
     * Opening quantities per warehouse. Only stock items can carry quantities, and because entering
     * them posts value to the ledger, only users who can approve postings may do so.
     *
     * @return array{lines: array<int, float>, unit_cost: float, date: string}
     */
    private function openingStock(Request $request, string $type): array
    {
        $data = $request->validate([
            'opening' => ['nullable', 'array'],
            'opening.*' => ['nullable', 'numeric', 'min:0'],
            'opening_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_date' => ['nullable', 'date'],
        ]);

        $lines = collect($data['opening'] ?? [])
            ->map(fn ($qty) => round((float) $qty, 4))
            ->filter(fn ($qty) => $qty > 0)
            ->all();

        if ($lines === []) {
            return ['lines' => [], 'unit_cost' => 0.0, 'date' => now()->toDateString()];
        }

        if ($type !== 'stock') {
            throw ValidationException::withMessages(['opening' => 'Only stock items can have quantities on hand.']);
        }

        if (!$request->user()->can('approve')) {
            throw ValidationException::withMessages(['opening' => 'Opening quantities post to the ledger and must be entered by a Finance Manager or Company Admin.']);
        }

        $validWarehouses = Warehouse::where('status', true)->whereIn('id', array_keys($lines))->pluck('id')->all();
        if (count($validWarehouses) !== count($lines)) {
            throw ValidationException::withMessages(['opening' => 'Choose an active store for every opening quantity.']);
        }

        return [
            'lines' => $lines,
            'unit_cost' => (float) ($data['opening_unit_cost'] ?? $request->input('standard_cost', 0)),
            'date' => $data['opening_date'] ?? now()->toDateString(),
        ];
    }

    private function receiveOpeningStock(Item $item, array $opening, InventoryStockService $stock, InventoryPostingService $ledger): void
    {
        if ($opening['lines'] === []) {
            return;
        }

        $value = 0.0;
        foreach ($opening['lines'] as $warehouseId => $quantity) {
            $movement = $stock->move($item->company_id, $item->id, (int) $warehouseId, 'opening_stock', $quantity, $opening['unit_cost'], $item);
            $value += InventoryStockService::value($movement);
        }

        $ledger->openingStock($item, $item->sku, $opening['date'], $value);
    }

    private function hasMovements(Item $item): bool
    {
        return StockMovement::where('item_id', $item->id)->exists();
    }

    private function message(string $prefix, array $opening): string
    {
        if ($opening['lines'] === []) {
            return $prefix.'.';
        }

        $quantity = array_sum($opening['lines']);

        return $prefix.' with '.rtrim(rtrim(number_format($quantity, 4), '0'), '.').' units on hand (value '.number_format($quantity * $opening['unit_cost'], 2).').';
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'sku' => ['required', 'max:80', Rule::unique('accounting_items')->where('company_id', $request->user()->company_id)->ignore($item)],
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(['stock', 'service', 'non_stock'])],
            'costing_method' => ['required', Rule::in(['fifo', 'weighted_average', 'standard'])],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'track_serials' => ['nullable', 'boolean'],
            'track_lots' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]) + [
            'track_serials' => false,
            'track_lots' => false,
            'status' => true,
        ];
    }
}
