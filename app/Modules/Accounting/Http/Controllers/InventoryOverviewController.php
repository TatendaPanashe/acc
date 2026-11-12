<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\InventoryStockService;
use App\Modules\Accounting\Services\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryOverviewController extends Controller
{
    public function __invoke(Request $request, InventoryStockService $stock, AccountMappingService $mappings, TrialBalanceService $trialBalance): View
    {
        try {
            $inventoryAccount = $mappings->account('inventory', (int) $request->user()->company_id);
            $glValue = (float) $trialBalance->between(null, now())->firstWhere('id', $inventoryAccount->id)?->balance;
        } catch (ValidationException) {
            $inventoryAccount = null;
            $glValue = null;
        }

        $balances = $stock->balances();
        $totalByItem = $balances->groupBy('item_id')->map(fn ($rows) => (float) $rows->sum('quantity_on_hand'));
        $reorderItems = Item::where('type', 'stock')
            ->where('status', true)
            ->orderBy('sku')
            ->get()
            ->filter(fn (Item $item) => ($totalByItem[$item->id] ?? 0) <= (float) $item->reorder_level);

        return view('accounting.inventory.overview', [
            'balances' => $balances,
            'reorderItems' => $reorderItems,
            'totalByItem' => $totalByItem,
            'stockValue' => $stock->totalValue(),
            'glValue' => $glValue,
            'inventoryAccount' => $inventoryAccount,
        ]);
    }
}
