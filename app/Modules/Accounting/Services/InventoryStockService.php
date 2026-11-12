<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Stock ledger. All unit costs are in the company's base currency.
 *
 * Costing: items with costing_method "standard" issue at standard cost; all others
 * (weighted_average, and fifo for now) issue at the moving weighted-average cost
 * across all warehouses.
 */
class InventoryStockService
{
    public function balances(): Collection
    {
        return StockMovement::query()
            ->selectRaw('item_id, warehouse_id, SUM(quantity) as quantity_on_hand, SUM(quantity * unit_cost) as stock_value')
            ->with(['item', 'warehouse'])
            ->groupBy('item_id', 'warehouse_id')
            ->orderBy('item_id')
            ->get()
            ->each(function ($row) {
                $qty = (float) $row->quantity_on_hand;
                $row->stock_value = round((float) $row->stock_value, 2);
                $row->average_cost = $qty > 0 ? round($row->stock_value / $qty, 4) : 0;
            });
    }

    /** Total value of stock on hand (sum of quantity × cost of every movement). */
    public function totalValue(): float
    {
        return round((float) StockMovement::query()->selectRaw('SUM(quantity * unit_cost) as value')->value('value'), 2);
    }

    public function available(int $itemId, int $warehouseId): float
    {
        return (float) StockMovement::withoutGlobalScopes()
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    public function averageCost(Item $item): float
    {
        $totals = StockMovement::withoutGlobalScopes()
            ->where('item_id', $item->id)
            ->selectRaw('SUM(quantity) as qty, SUM(quantity * unit_cost) as value')
            ->first();

        $qty = (float) ($totals->qty ?? 0);

        return $qty > 0 ? round((float) $totals->value / $qty, 4) : (float) $item->standard_cost;
    }

    /** Cost per unit for stock leaving the warehouse. */
    public function issueCost(Item $item): float
    {
        return $item->costing_method === 'standard' ? (float) $item->standard_cost : $this->averageCost($item);
    }

    /**
     * Records a stock movement. Positive quantity = into stock (unit cost required);
     * negative = out of stock (unit cost defaults to the item's issue cost).
     * The item row is locked so concurrent issues cannot oversell.
     */
    public function move(
        int $companyId,
        int $itemId,
        int $warehouseId,
        string $type,
        float $quantity,
        ?float $unitCost = null,
        ?Model $source = null,
    ): StockMovement {
        $item = Item::withoutGlobalScopes()->lockForUpdate()->findOrFail($itemId);

        if ($quantity < 0 && $this->available($itemId, $warehouseId) < abs($quantity)) {
            throw ValidationException::withMessages([
                'stock' => "Insufficient stock of {$item->sku} in the selected warehouse.",
            ]);
        }

        $movement = new StockMovement([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost ?? $this->issueCost($item),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'moved_at' => now(),
        ]);
        $movement->company_id = $companyId;
        $movement->save();

        return $movement;
    }

    public static function value(StockMovement $movement): float
    {
        return round(abs((float) $movement->quantity) * (float) $movement->unit_cost, 2);
    }
}
