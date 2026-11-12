<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\PosSale;
use App\Modules\Accounting\Models\StockMovement;
use App\Modules\Accounting\Models\TaxCode;
use App\Modules\Accounting\Models\Warehouse;
use App\Modules\Accounting\Services\CommercialPostingService;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Accounting\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosSaleController extends Controller
{
    public function index(): View
    {
        return view('accounting.pos.index', [
            'sales' => PosSale::with(['customer', 'warehouse', 'cashier'])->latest('sold_at')->paginate(30),
        ]);
    }

    public function create(Request $request, ExchangeRateService $rates): View
    {
        $stock = StockMovement::query()
            ->selectRaw('item_id, warehouse_id, SUM(quantity) as qty')
            ->groupBy('item_id', 'warehouse_id')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->warehouse_id => round((float) $row->qty, 4)]));

        $vat = TaxCode::where('status', true)->where('code', 'VAT-STD')->value('rate')
            ?? TaxCode::where('status', true)->whereIn('type', ['sales', 'both'])->orderByDesc('rate')->value('rate')
            ?? 0;

        return view('accounting.pos.create', [
            'customers' => Customer::where('status', true)->orderBy('name')->get(),
            'items' => Item::where('status', true)->orderBy('sku')->get(),
            'warehouses' => Warehouse::where('status', true)->orderBy('code')->get(),
            'stockLevels' => $stock,
            'defaultTaxRate' => (float) $vat,
            'currencies' => Currency::where('status', true)->orderByDesc('is_base')->orderBy('code')->pluck('code'),
            'baseCurrency' => $rates->baseCurrency((int) $request->user()->company_id),
        ]);
    }

    public function store(Request $request, InventoryStockService $stock, CommercialPostingService $posting): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', new TenantExists('accounting_customers')],
            'warehouse_id' => ['required', new TenantExists('accounting_warehouses')],
            'receipt_number' => ['required', 'max:60', Rule::unique('accounting_pos_sales')->where('company_id', $request->user()->company_id)],
            'sold_at' => ['required', 'date'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'payment_method' => ['required', 'max:30'],
            'amount_tendered' => ['required', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', new TenantExists('accounting_items')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = DB::transaction(function () use ($data, $request, $stock, $posting) {
            $items = Item::whereIn('id', collect($data['lines'])->pluck('item_id'))->get()->keyBy('id');
            $subtotal = 0;
            $taxTotal = 0;
            $discountTotal = 0;
            $preparedLines = [];

            foreach ($data['lines'] as $line) {
                $item = $items[$line['item_id']];
                $quantity = (float) $line['quantity'];
                $gross = round($quantity * (float) $line['unit_price'], 2);
                $discount = min((float) ($line['discount'] ?? 0), $gross);
                $net = $gross - $discount;
                $tax = round($net * (float) ($line['tax_rate'] ?? 0) / 100, 2);
                $unitCost = $item->type === 'stock' ? $stock->issueCost($item) : 0.0;
                $costTotal = round($quantity * $unitCost, 2);

                $subtotal += $gross;
                $discountTotal += $discount;
                $taxTotal += $tax;

                $preparedLines[] = [
                    'company_id' => $request->user()->company_id,
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => $quantity,
                    'unit_price' => $line['unit_price'],
                    'discount' => $discount,
                    'tax_rate' => $line['tax_rate'] ?? 0,
                    'line_total' => $net,
                    'tax_amount' => $tax,
                    'cost_total' => $costTotal,
                    'item' => $item,
                    'unit_cost' => $unitCost,
                ];
            }

            $total = $subtotal - $discountTotal + $taxTotal;
            $amountTendered = (float) $data['amount_tendered'];
            if ($amountTendered < $total) {
                throw ValidationException::withMessages([
                    'amount_tendered' => 'Amount tendered must cover the sale total.',
                ]);
            }

            $sale = PosSale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'cashier_id' => $request->user()->id,
                'receipt_number' => $data['receipt_number'],
                'sold_at' => $data['sold_at'],
                'currency' => strtoupper($data['currency']),
                'payment_method' => $data['payment_method'],
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'amount_tendered' => $amountTendered,
                'change_due' => max(0, $amountTendered - $total),
                'status' => 'posted',
            ]);

            foreach ($preparedLines as $line) {
                $item = $line['item'];
                $unitCost = $line['unit_cost'];
                unset($line['item'], $line['unit_cost']);
                $sale->lines()->create($line);
                if ($item->type === 'stock') {
                    $stock->move($sale->company_id, $item->id, $sale->warehouse_id, 'pos_sale', -1 * (float) $line['quantity'], $unitCost, $sale);
                }
            }

            $posting->postPosSale($sale);

            return $sale;
        });

        return redirect()->route('accounting.pos.receipt', $sale)->with('success', 'POS sale completed.');
    }

    public function show(PosSale $sale): View
    {
        $sale->load(['customer', 'warehouse', 'cashier', 'lines.item']);

        return view('accounting.pos.show', compact('sale'));
    }

    public function receipt(PosSale $sale): View
    {
        $sale->load(['company', 'customer', 'warehouse', 'cashier', 'lines.item']);

        return view('accounting.pos.receipt', compact('sale'));
    }
}
