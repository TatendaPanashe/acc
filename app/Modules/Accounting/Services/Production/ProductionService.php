<?php

namespace App\Modules\Accounting\Services\Production;

use App\Modules\Accounting\Models\Bom;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\WorkOrder;
use App\Modules\Accounting\Models\WorkOrderMaterial;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bills of materials and work orders.
 *
 * Costing: a component that has its own active BOM is a sub-assembly and is costed by rolling up that BOM;
 * other components use their current issue cost (weighted average or standard). Work orders consume direct
 * components only (sub-assemblies are made on their own work orders and must be in stock).
 *
 * Ledger: issue materials  Dr WIP / Cr Inventory
 *         labour, overhead Dr WIP / Cr Labour & overhead applied
 *         completion       Dr Inventory (finished goods) / Cr WIP, at actual cost
 */
class ProductionService
{
    private const MAX_DEPTH = 10;

    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly InventoryPostingService $ledger,
    ) {
    }

    // ---- BOMs ---------------------------------------------------------------------

    public function saveBom(Bom $bom, array $data, array $lines): Bom
    {
        return DB::transaction(function () use ($bom, $data, $lines) {
            $bom->fill($data);
            foreach ($lines as $line) {
                if ((int) $line['item_id'] === (int) $bom->item_id) {
                    throw ValidationException::withMessages(['lines' => 'A BOM cannot use the item it makes.']);
                }
            }
            $this->assertNoCycle((int) $bom->item_id, collect($lines)->pluck('item_id')->map(fn ($id) => (int) $id)->all());
            $bom->save();

            if ($bom->is_active) { // one active BOM per item
                Bom::where('item_id', $bom->item_id)->whereKeyNot($bom->id)->update(['is_active' => false]);
            }
            $bom->lines()->delete();
            foreach ($lines as $line) {
                $bom->lines()->create($line + ['company_id' => $bom->company_id]);
            }

            return $bom->fresh('lines.item');
        });
    }

    /** Throws if making $itemId would (through sub-assemblies) require $itemId itself. */
    private function assertNoCycle(int $itemId, array $componentIds, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['lines' => 'The BOM structure is more than '.self::MAX_DEPTH.' levels deep.']);
        }
        foreach ($componentIds as $componentId) {
            if ($componentId === $itemId) {
                throw ValidationException::withMessages(['lines' => 'This BOM would make the item a component of itself (circular BOM).']);
            }
            $sub = $this->activeBomFor($componentId);
            if ($sub) {
                $this->assertNoCycle($itemId, $sub->lines->pluck('item_id')->map(fn ($id) => (int) $id)->all(), $depth + 1);
            }
        }
    }

    public function activeBomFor(int $itemId): ?Bom
    {
        return Bom::with('lines.item')->where('item_id', $itemId)->where('is_active', true)->first();
    }

    /**
     * Standard cost of one BOM batch, rolled up through sub-assemblies.
     * @return array{lines: Collection, materials: float, labour: float, overhead: float, total: float, unit_cost: float}
     */
    public function costRollup(Bom $bom, int $depth = 0): array
    {
        $bom->loadMissing('lines.item');
        $lines = $bom->lines->map(function ($line) use ($depth) {
            $sub = $depth < self::MAX_DEPTH ? $this->activeBomFor((int) $line->item_id) : null;
            $unit = $sub ? $this->costRollup($sub, $depth + 1)['unit_cost'] : $this->stock->issueCost($line->item);

            return [
                'item' => $line->item, 'quantity' => (float) $line->quantity, 'scrap_percent' => (float) $line->scrap_percent,
                'gross' => $line->grossQuantity(), 'unit_cost' => round($unit, 4), 'cost' => round($line->grossQuantity() * $unit, 2),
                'source' => $sub ? 'Sub-assembly ('.$sub->code.')' : 'Stock cost',
            ];
        });

        $materials = round($lines->sum('cost'), 2);
        $labour = round((float) $bom->labour_hours * (float) $bom->labour_rate, 2);
        $overhead = round((float) $bom->labour_hours * (float) $bom->overhead_rate, 2);
        $total = round($materials + $labour + $overhead, 2);

        return ['lines' => $lines, 'materials' => $materials, 'labour' => $labour, 'overhead' => $overhead, 'total' => $total,
            'unit_cost' => (float) $bom->output_quantity > 0 ? round($total / (float) $bom->output_quantity, 4) : $total];
    }

    /** Raw materials needed for $quantity of the BOM's item, through all levels, with stock on hand. */
    public function explode(Bom $bom, float $quantity, int $depth = 0, array &$totals = []): array
    {
        $bom->loadMissing('lines.item');
        $batches = (float) $bom->output_quantity > 0 ? $quantity / (float) $bom->output_quantity : $quantity;
        foreach ($bom->lines as $line) {
            $needed = round($line->grossQuantity() * $batches, 4);
            $sub = $depth < self::MAX_DEPTH ? $this->activeBomFor((int) $line->item_id) : null;
            if ($sub) {
                $this->explode($sub, $needed, $depth + 1, $totals);
                continue;
            }
            $totals[$line->item_id] = ['item' => $line->item, 'quantity' => round(($totals[$line->item_id]['quantity'] ?? 0) + $needed, 4),
                'on_hand' => $line->item->quantity_on_hand];
        }

        return $totals;
    }

    // ---- work orders ---------------------------------------------------------------

    public function createWorkOrder(array $data): WorkOrder
    {
        $bom = Bom::with('item')->findOrFail($data['bom_id']);
        if ($bom->item?->type !== 'stock') {
            throw ValidationException::withMessages(['bom_id' => 'Work orders can only make stock items.']);
        }

        return WorkOrder::create($data + ['item_id' => $bom->item_id, 'status' => 'draft', 'created_by' => auth()->id()]);
    }

    /** Fixes the material requirements and standard cost; reports shortages (does not reserve stock). */
    public function release(WorkOrder $order): array
    {
        return DB::transaction(function () use ($order) {
            $order = WorkOrder::lockForUpdate()->findOrFail($order->id);
            $this->assertStatus($order, ['draft']);
            $bom = $order->bom()->with('lines.item')->firstOrFail();
            $batches = (float) $bom->output_quantity > 0 ? (float) $order->quantity / (float) $bom->output_quantity : (float) $order->quantity;

            $shortages = [];
            foreach ($bom->lines as $line) {
                $required = round($line->grossQuantity() * $batches, 4);
                $order->materials()->create(['company_id' => $order->company_id, 'item_id' => $line->item_id, 'required_quantity' => $required]);
                $available = $this->stock->available((int) $line->item_id, (int) $order->warehouse_id);
                if ($available < $required) {
                    $shortages[] = $line->item->sku.' short by '.rtrim(rtrim(number_format($required - $available, 4), '0'), '.');
                }
            }
            $order->update(['status' => 'released', 'released_at' => now(), 'standard_unit_cost' => $this->costRollup($bom)['unit_cost']]);

            return $shortages;
        });
    }

    /** Issues the remaining (or given) quantity of each material from the work order's warehouse. */
    public function issueMaterials(WorkOrder $order, ?array $quantities = null): float
    {
        return DB::transaction(function () use ($order, $quantities) {
            $order = WorkOrder::lockForUpdate()->with('materials.item')->findOrFail($order->id);
            $this->assertStatus($order, ['released', 'in_progress']);
            $cost = 0.0;

            foreach ($order->materials as $material) {
                $qty = round($quantities === null ? $material->remaining() : min(max(0.0, (float) ($quantities[$material->id] ?? 0)), $material->remaining()), 4);
                if ($qty <= 0) {
                    continue;
                }
                $movement = $this->stock->move($order->company_id, $material->item_id, $order->warehouse_id, 'work_order_issue', -1 * $qty, null, $order);
                $value = InventoryStockService::value($movement);
                $material->update(['issued_quantity' => round((float) $material->issued_quantity + $qty, 4), 'issued_cost' => round((float) $material->issued_cost + $value, 2)]);
                $cost += $value;
            }

            if ($cost <= 0) {
                throw ValidationException::withMessages(['materials' => 'Nothing to issue.']);
            }
            $sequence = \App\Modules\Accounting\Models\JournalEntry::where('source_type', WorkOrder::class)->where('source_id', $order->id)->count() + 1;
            $this->ledger->transfer($order, $order->wo_number.'-ISS-'.$sequence, now()->toDateString(), 'wip', 'inventory', $cost, 'Materials issued to '.$order->wo_number);
            $order->update(['status' => 'in_progress', 'material_cost' => round((float) $order->material_cost + $cost, 2)]);

            return round($cost, 2);
        });
    }

    /**
     * Completes the work order: applies labour and overhead for the hours worked (default: BOM hours for
     * the quantity made) and receives the finished goods at actual cost.
     */
    public function complete(WorkOrder $order, float $completedQuantity, ?float $labourHours = null): WorkOrder
    {
        return DB::transaction(function () use ($order, $completedQuantity, $labourHours) {
            $order = WorkOrder::lockForUpdate()->with('bom')->findOrFail($order->id);
            $this->assertStatus($order, ['in_progress']);
            if ($completedQuantity <= 0) {
                throw ValidationException::withMessages(['completed_quantity' => 'Enter the quantity made.']);
            }

            $bom = $order->bom;
            $batches = (float) $bom->output_quantity > 0 ? $completedQuantity / (float) $bom->output_quantity : $completedQuantity;
            $hours = round($labourHours ?? (float) $bom->labour_hours * $batches, 4);
            $labour = round($hours * (float) $bom->labour_rate, 2);
            $overhead = round($hours * (float) $bom->overhead_rate, 2);
            $date = now()->toDateString();

            $this->ledger->transfer($order, $order->wo_number.'-LAB', $date, 'wip', 'labour_applied', $labour, 'Labour applied to '.$order->wo_number);
            $this->ledger->transfer($order, $order->wo_number.'-OH', $date, 'wip', 'overhead_applied', $overhead, 'Overhead applied to '.$order->wo_number);

            $total = round((float) $order->material_cost + $labour + $overhead, 2);
            $unitCost = round($total / $completedQuantity, 4);
            $this->stock->move($order->company_id, $order->item_id, $order->warehouse_id, 'work_order_receipt', $completedQuantity, $unitCost, $order);
            $this->ledger->transfer($order, $order->wo_number.'-FG', $date, 'inventory', 'wip', $total, 'Finished goods from '.$order->wo_number);

            $order->update([
                'status' => 'completed', 'completed_at' => now(), 'completed_quantity' => $completedQuantity,
                'labour_hours' => $hours, 'labour_cost' => $labour, 'overhead_cost' => $overhead,
            ]);

            return $order->fresh();
        });
    }

    public function cancel(WorkOrder $order): void
    {
        $this->assertStatus($order, ['draft', 'released']);
        $order->update(['status' => 'cancelled']);
    }

    public function nextNumber(): string
    {
        $last = WorkOrder::where('wo_number', 'like', 'WO-%')->orderByDesc('id')->value('wo_number');

        return 'WO-'.str_pad((string) ((int) substr((string) $last, 3) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function assertStatus(WorkOrder $order, array $allowed): void
    {
        if (!in_array($order->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Work order '.$order->wo_number.' is '.str_replace('_', ' ', $order->status).'.']);
        }
    }

    public static function stockItems(): Collection
    {
        return Item::where('status', true)->where('type', 'stock')->orderBy('sku')->get(['id', 'sku', 'name', 'standard_cost']);
    }
}
