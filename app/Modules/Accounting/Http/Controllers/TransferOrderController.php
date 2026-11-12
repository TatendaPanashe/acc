<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\StockMovement;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\TransferOrder;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransferOrderController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.transfers.index', [
            'transfers' => TransferOrder::with(['fromWarehouse', 'toWarehouse'])->latest('transfer_date')->paginate(25),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'items' => Item::where('status', true)->where('type', 'stock')->orderBy('sku')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transfer_number' => ['required', 'max:60', Rule::unique('accounting_transfer_orders')->where('company_id', $request->user()->company_id)],
            'from_warehouse_id' => ['required', new TenantExists('accounting_warehouses'), 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', new TenantExists('accounting_items')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $transfer = TransferOrder::create([
                'transfer_number' => $data['transfer_number'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $transfer->lines()->create([
                    'company_id' => $request->user()->company_id,
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                ]);
            }
        });

        return back()->with('success', 'Transfer order created.');
    }

    public function show(TransferOrder $transferOrder): View
    {
        $transferOrder->load(['fromWarehouse', 'toWarehouse', 'lines.item']);

        return view('accounting.inventory.transfers.show', ['transfer' => $transferOrder]);
    }

    public function ship(TransferOrder $transferOrder, InventoryStockService $stock): RedirectResponse
    {
        DB::transaction(function () use ($transferOrder, $stock) {
            $transfer = TransferOrder::lockForUpdate()->findOrFail($transferOrder->id);
            abort_if($transfer->status !== 'draft', 422, 'Only draft transfers can be shipped.');
            $transfer->load('lines.item');

            foreach ($transfer->lines as $line) {
                $stock->move($transfer->company_id, $line->item_id, $transfer->from_warehouse_id, 'transfer_ship', -1 * (float) $line->quantity, null, $transfer);
            }
            $transfer->update(['status' => 'in_transit']);
        });

        return back()->with('success', 'Transfer shipped from source warehouse.');
    }

    /** Received at the same unit cost it was shipped at, so total stock value is unchanged. */
    public function receive(TransferOrder $transferOrder, InventoryStockService $stock): RedirectResponse
    {
        DB::transaction(function () use ($transferOrder, $stock) {
            $transfer = TransferOrder::lockForUpdate()->findOrFail($transferOrder->id);
            abort_if($transfer->status !== 'in_transit', 422, 'Only in-transit transfers can be received.');
            $transfer->load('lines.item');

            $shippedCost = StockMovement::where('source_type', $transfer->getMorphClass())
                ->where('source_id', $transfer->id)
                ->where('movement_type', 'transfer_ship')
                ->pluck('unit_cost', 'item_id');

            foreach ($transfer->lines as $line) {
                $stock->move($transfer->company_id, $line->item_id, $transfer->to_warehouse_id, 'transfer_receive', (float) $line->quantity, (float) ($shippedCost[$line->item_id] ?? $stock->issueCost($line->item)), $transfer);
            }
            $transfer->update(['status' => 'received']);
        });

        return back()->with('success', 'Transfer received into destination warehouse.');
    }
}
