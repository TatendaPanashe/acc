<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\SalesOrder;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.sales_orders.index', [
            'orders' => SalesOrder::with('customer')->latest('order_date')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('accounting.inventory.sales_orders.create', $this->formData(new SalesOrder([
            'order_date' => now(),
            'required_date' => now()->addDays(7),
            'currency' => 'USD',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $order = DB::transaction(fn () => $this->saveOrder(new SalesOrder(), $this->validated($request), $request->user()->company_id));

        return redirect()->route('accounting.inventory.sales-orders.show', $order)->with('success', 'Sales order saved.');
    }

    public function show(SalesOrder $salesOrder): View
    {
        $salesOrder->load(['customer', 'lines.item', 'lines.warehouse']);

        return view('accounting.inventory.sales_orders.show', ['order' => $salesOrder]);
    }

    public function edit(SalesOrder $salesOrder): View|RedirectResponse
    {
        if ($salesOrder->status !== 'draft') {
            return redirect()->route('accounting.inventory.sales-orders.show', $salesOrder)->with('warning', 'Only draft sales orders can be edited.');
        }

        $salesOrder->load('lines');

        return view('accounting.inventory.sales_orders.edit', $this->formData($salesOrder));
    }

    public function update(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        abort_if($salesOrder->status !== 'draft', 422, 'Only draft sales orders can be edited.');
        DB::transaction(fn () => $this->saveOrder($salesOrder, $this->validated($request, $salesOrder), $request->user()->company_id));

        return redirect()->route('accounting.inventory.sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        abort_if($salesOrder->status !== 'draft', 422, 'Only draft sales orders can be deleted.');
        $salesOrder->delete();

        return redirect()->route('accounting.inventory.sales-orders.index')->with('success', 'Sales order deleted.');
    }

    public function confirm(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->status === 'draft') {
            $salesOrder->update(['status' => 'confirmed']);
        }

        return back()->with('success', 'Sales order confirmed.');
    }

    public function fulfill(SalesOrder $salesOrder, InventoryStockService $stock, InventoryPostingService $ledger): RedirectResponse
    {
        DB::transaction(function () use ($salesOrder, $stock, $ledger) {
            $order = SalesOrder::lockForUpdate()->findOrFail($salesOrder->id);
            abort_if($order->status !== 'confirmed', 422, 'Only confirmed sales orders can be fulfilled.');
            $order->load('lines.item');
            $cost = 0.0;

            foreach ($order->lines as $line) {
                if ($line->item->type === 'stock') {
                    $movement = $stock->move($order->company_id, $line->item_id, $line->warehouse_id, 'sales_order_fulfillment', -1 * (float) $line->quantity, null, $order);
                    $cost += InventoryStockService::value($movement);
                }
            }

            $ledger->salesIssue($order, $order->order_number, now()->toDateString(), $cost);
            $order->update(['status' => 'fulfilled']);
        });

        return back()->with('success', 'Sales order fulfilled; stock issued and cost of sales posted.');
    }

    private function formData(SalesOrder $order): array
    {
        return [
            'order' => $order,
            'customers' => Customer::where('status', true)->orderBy('name')->get(),
            'items' => Item::where('status', true)->orderBy('sku')->get(),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request, ?SalesOrder $order = null): array
    {
        return $request->validate([
            'customer_id' => ['required', new TenantExists('accounting_customers')],
            'order_number' => ['required', 'max:60', Rule::unique('accounting_sales_orders')->where('company_id', $request->user()->company_id)->ignore($order)],
            'order_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'notes' => ['nullable', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', new TenantExists('accounting_items')],
            'lines.*.warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'lines.*.description' => ['nullable', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function saveOrder(SalesOrder $order, array $data, int $companyId): SalesOrder
    {
        [$subtotal, $taxTotal] = $this->totals($data['lines'], 'unit_price');
        $order->fill([
            'customer_id' => $data['customer_id'],
            'order_number' => $data['order_number'],
            'order_date' => $data['order_date'],
            'required_date' => $data['required_date'] ?? null,
            'currency' => strtoupper($data['currency']),
            'notes' => $data['notes'] ?? null,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
            'status' => 'draft',
        ])->save();

        $order->lines()->delete();
        foreach ($data['lines'] as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $order->lines()->create([
                'company_id' => $companyId,
                'item_id' => $line['item_id'],
                'warehouse_id' => $line['warehouse_id'],
                'description' => $line['description'] ?? null,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_rate' => $line['tax_rate'] ?? 0,
                'line_total' => $lineTotal,
            ]);
        }

        return $order;
    }

    private function totals(array $lines, string $priceKey): array
    {
        $subtotal = 0;
        $taxTotal = 0;
        foreach ($lines as $line) {
            $lineTotal = round((float) $line['quantity'] * (float) $line[$priceKey], 2);
            $subtotal += $lineTotal;
            $taxTotal += round($lineTotal * (float) ($line['tax_rate'] ?? 0) / 100, 2);
        }

        return [$subtotal, $taxTotal];
    }
}
