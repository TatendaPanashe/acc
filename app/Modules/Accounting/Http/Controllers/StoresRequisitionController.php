<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\StoresRequisition;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoresRequisitionController extends Controller
{
    public function index(): View
    {
        return view('accounting.inventory.requisitions.index', [
            'requisitions' => StoresRequisition::with(['warehouse', 'requester'])->latest()->paginate(25),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'items' => Item::where('status', true)->where('type', 'stock')->orderBy('sku')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requisition_number' => ['required', 'max:60', Rule::unique('accounting_stores_requisitions')->where('company_id', $request->user()->company_id)],
            'warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'department' => ['nullable', 'max:120'],
            'required_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', new TenantExists('accounting_items')],
            'lines.*.description' => ['nullable', 'max:255'],
            'lines.*.quantity_requested' => ['required', 'numeric', 'min:0.0001'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $requisition = StoresRequisition::create([
                'requisition_number' => $data['requisition_number'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_by' => $request->user()->id,
                'department' => $data['department'] ?? null,
                'required_date' => $data['required_date'] ?? null,
                'purpose' => $data['purpose'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                $requisition->lines()->create([
                    'company_id' => $request->user()->company_id,
                    'item_id' => $line['item_id'],
                    'description' => $line['description'] ?? null,
                    'quantity_requested' => $line['quantity_requested'],
                ]);
            }
        });

        return back()->with('success', 'Stores requisition created.');
    }

    public function show(StoresRequisition $requisition): View
    {
        $requisition->load(['warehouse', 'requester', 'lines.item']);

        return view('accounting.inventory.requisitions.show', compact('requisition'));
    }

    public function approve(StoresRequisition $requisition): RedirectResponse
    {
        if ($requisition->status === 'draft') {
            $requisition->update(['status' => 'approved']);
        }

        return back()->with('success', 'Stores requisition approved.');
    }

    public function issue(StoresRequisition $requisition, InventoryStockService $stock, InventoryPostingService $ledger): RedirectResponse
    {
        DB::transaction(function () use ($requisition, $stock, $ledger) {
            $locked = StoresRequisition::lockForUpdate()->findOrFail($requisition->id);
            abort_if($locked->status !== 'approved', 422, 'Only approved requisitions can be issued.');
            $locked->load('lines.item');
            $cost = 0.0;

            foreach ($locked->lines as $line) {
                $quantity = (float) $line->quantity_requested;
                $movement = $stock->move($locked->company_id, $line->item_id, $locked->warehouse_id, 'stores_requisition_issue', -1 * $quantity, null, $locked);
                $cost += InventoryStockService::value($movement);
                $line->update(['quantity_issued' => $quantity]);
            }

            $ledger->storesIssue($locked, $locked->requisition_number ?? (string) $locked->id, now()->toDateString(), $cost);
            $locked->update(['status' => 'issued']);
        });

        return back()->with('success', 'Stores requisition issued; stock reduced and consumption posted.');
    }
}
