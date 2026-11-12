<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\PurchaseOrder;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.purchase_orders.index', [
            'orders' => PurchaseOrder::with('supplier')->latest('order_date')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('accounting.inventory.purchase_orders.create', $this->formData(new PurchaseOrder([
            'order_date' => now(),
            'expected_date' => now()->addDays(14),
            'currency' => 'USD',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = DB::transaction(fn () => $this->saveOrder(new PurchaseOrder(), $this->validated($request), $request->user()->company_id));

        return redirect()->route('accounting.inventory.purchase-orders.show', $order)->with('success', 'Purchase order saved.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'lines.item', 'lines.warehouse']);

        return view('accounting.inventory.purchase_orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('accounting.inventory.purchase-orders.show', $purchaseOrder)->with('warning', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->load('lines');

        return view('accounting.inventory.purchase_orders.edit', $this->formData($purchaseOrder));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->status !== 'draft', 422, 'Only draft purchase orders can be edited.');
        DB::transaction(fn () => $this->saveOrder($purchaseOrder, $this->validated($request, $purchaseOrder), $request->user()->company_id));

        return redirect()->route('accounting.inventory.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_if($purchaseOrder->status !== 'draft', 422, 'Only draft purchase orders can be deleted.');
        $purchaseOrder->delete();

        return redirect()->route('accounting.inventory.purchase-orders.index')->with('success', 'Purchase order deleted.');
    }

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'draft') {
            $purchaseOrder->update(['status' => 'approved']);
        }

        return back()->with('success', 'Purchase order approved.');
    }

    public function receive(PurchaseOrder $purchaseOrder, InventoryStockService $stock, InventoryPostingService $ledger, ExchangeRateService $rates): RedirectResponse
    {
        DB::transaction(function () use ($purchaseOrder, $stock, $ledger, $rates) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_if($order->status !== 'approved', 422, 'Only approved purchase orders can be received.');
            $order->load('lines.item');

            $receivedOn = now()->toDateString();
            $rate = $rates->rate($order->currency, $order->company_id, $receivedOn);
            $value = 0.0;

            foreach ($order->lines as $line) {
                if ($line->item->type === 'stock') {
                    $movement = $stock->move($order->company_id, $line->item_id, $line->warehouse_id, 'purchase_order_receipt', (float) $line->quantity, round((float) $line->unit_cost * $rate, 4), $order);
                    $value += InventoryStockService::value($movement);
                }
            }

            $ledger->goodsReceived($order, $order->order_number, $receivedOn, $value);
            $order->update(['status' => 'received']);
        });

        return back()->with('success', 'Purchase order received into stock and posted to inventory.');
    }

    private function formData(PurchaseOrder $order): array
    {
        return [
            'order' => $order,
            'suppliers' => Supplier::where('status', true)->orderBy('name')->get(),
            'items' => Item::where('status', true)->orderBy('sku')->get(),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request, ?PurchaseOrder $order = null): array
    {
        return $request->validate([
            'supplier_id' => ['required', new TenantExists('accounting_suppliers')],
            'order_number' => ['required', 'max:60', Rule::unique('accounting_purchase_orders')->where('company_id', $request->user()->company_id)->ignore($order)],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'notes' => ['nullable', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', new TenantExists('accounting_items')],
            'lines.*.warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'lines.*.description' => ['nullable', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function saveOrder(PurchaseOrder $order, array $data, int $companyId): PurchaseOrder
    {
        [$subtotal, $taxTotal] = $this->totals($data['lines']);
        $order->fill([
            'supplier_id' => $data['supplier_id'],
            'order_number' => $data['order_number'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'currency' => strtoupper($data['currency']),
            'notes' => $data['notes'] ?? null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
            'status' => 'draft',
        ])->save();

        $order->lines()->delete();
        foreach ($data['lines'] as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
            $order->lines()->create([
                'company_id' => $companyId,
                'item_id' => $line['item_id'],
                'warehouse_id' => $line['warehouse_id'],
                'description' => $line['description'] ?? null,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'tax_rate' => $line['tax_rate'] ?? 0,
                'line_total' => $lineTotal,
            ]);
        }

        return $order;
    }

    private function totals(array $lines): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($lines as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
            $subtotal += $lineTotal;
            $taxTotal += round($lineTotal * (float) ($line['tax_rate'] ?? 0) / 100, 2);
        }

        return [$subtotal, $taxTotal];
    }
}
