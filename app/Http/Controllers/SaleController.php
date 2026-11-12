<?php

namespace App\Http\Controllers;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer; // Optional
use App\Models\employee; // For linking the user who made the sale
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;
use Carbon\Carbon; // For sale_date

class SaleController extends Controller
{
    /**
     * Display the POS interface.
     * @return \Illuminate\Http\Response
     */
    public function posIndex()
    {
        $products = Product::active()->get(); // Only active products for sale
        $customers = Customer::all(); // Optional
        return view('pages.inventory.pos.index', compact('products', 'customers'));
    }

    /**
     * Process a new sale.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processSale(Request $request)
    {
        $request->validate([
            'customer_id'    => 'nullable|exists:customers,id',
            'payment_method' => 'required|string|max:50',
            'cart_items'     => 'required|array|min:1',
            'cart_items.*.product_id' => 'required|exists:products,id',
            'cart_items.*.quantity'   => 'required|integer|min:1',
            'total_amount'   => 'required|numeric|min:0',
            'paid_amount'    => 'required|numeric|min:0|gte:total_amount', // Paid amount must be >= total
        ]);

        try {
            DB::beginTransaction();

            $sale = Sale::create([
                'sale_date'      => Carbon::now(),
                'customer_id'    => $request->customer_id,
                'total_amount'   => $request->total_amount,
                'paid_amount'    => $request->paid_amount,
                'change_amount'  => $request->paid_amount - $request->total_amount,
                'payment_method' => $request->payment_method,
                'status'         => 'completed', // Default to completed on success
                'user_id'        => auth()->id(), // User who made the sale (assuming authenticated)
            ]);

            foreach ($request->cart_items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product || $product->stock_quantity < $item['quantity']) {
                    DB::rollback();
                    return back()->with('error', 'Insufficient stock for ' . ($product->name ?? 'a product') . '.')->withInput();
                }

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal'   => $product->price * $item['quantity'],
                ]);

                // Decrement stock quantity
                $product->decrement('stock_quantity', $item['quantity']);
            }

            DB::commit();

            return redirect()->route('pages.inventory.pos.sales', $sale->id)->with('success', 'Sale processed successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Error processing sale: ' . $e->getMessage());
        }
    }

    /**
     * Display a listing of sales.
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $sales = Sale::with(['customer', 'user'])->latest()->paginate(10);
        return view('pages.inventory.pos.history', compact('sales'));
    }

    /**
     * Display the specified sale.
     * @param  \App\Models\Sale  $sale
     * @return \Illuminate\Http\Response
     */
    public function show(Sale $sale)
    {
        $sale->load(['saleItems.product', 'customer', 'user']);
        return view('pages.inventory.pos.history', compact('sale'));
    }

    /**
     * Print a receipt for the specified sale.
     * @param  \App\Models\Sale  $sale
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function printReceipt(Sale $sale)
    {
        $sale->load(['saleItems.product', 'customer', 'user']); // Load relations needed for receipt

        // --- Company Information (Placeholder - pull from config or Company model) ---
        $companyInfo = [
            'name'    => 'Your Company POS',
            'address' => '123 Retail Street, City, Country',
            'phone'   => '+123 456 7890',
            'email'   => 'sales@yourcompany.com',
            // 'logo'    => public_path('images/pos_logo.png'), // Optional logo for receipt
        ];

        $receiptData = [
            'company'    => $companyInfo,
            'sale'       => $sale,
            'sale_items' => $sale->saleItems,
            'customer'   => $sale->customer,
            'cashier'    => $sale->user,
            'currency'   => 'USD', // Hardcoded, adjust if you have a dynamic currency setting
        ];

        $html = View::make('pages.inventory.pos.receipt', compact('receiptData'))->render();

        $pdf = PDF::loadHTML($html);

        // Optional: Set PDF options for receipt
        $pdf->setOptions([
            'margin-top'    => 5,
            'margin-right'  => 5,
            'margin-bottom' => 5,
            'margin-left'   => 5,
            'defaultFont'   => 'sans-serif',
        ]);

        $filename = 'receipt_' . $sale->sale_number . '.pdf';
        return $pdf->download($filename);
    }
}
