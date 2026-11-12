<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Item;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\PaymentRun;
use App\Modules\Accounting\Models\PurchaseOrder;
use App\Modules\Accounting\Models\SalesOrder;
use App\Modules\Accounting\Models\StoresRequisition;
use App\Modules\Accounting\Models\Supplier;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use Illuminate\View\View;

class AccountingDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('accounting.dashboard', [
            'metrics' => [
                ['label' => 'GL Accounts', 'value' => Account::count(), 'icon' => 'book', 'colour' => 'l-bg-purple'],
                ['label' => 'Posted Journals', 'value' => JournalEntry::where('status', 'posted')->count(), 'icon' => 'check-circle', 'colour' => 'l-bg-green'],
                ['label' => 'Customers', 'value' => Customer::count(), 'icon' => 'users', 'colour' => 'l-bg-cyan'],
                ['label' => 'Inventory Items', 'value' => Item::count(), 'icon' => 'archive', 'colour' => 'l-bg-orange'],
            ],
            'recentJournals' => JournalEntry::latest('entry_date')->latest()->take(8)->get(),
            'suppliers' => Supplier::latest()->take(5)->get(),
            'approvalQueue' => $this->approvalQueue(),
        ]);
    }

    /** Draft documents that only a Finance Manager / Company Admin can approve or post. */
    private function approvalQueue(): array
    {
        return array_filter([
            ['label' => 'Journal entries', 'route' => 'accounting.gl.journals.index', 'count' => JournalEntry::where('status', 'draft')->count()],
            ['label' => 'Supplier invoices', 'route' => 'accounting.ap.supplier-invoices.index', 'count' => SupplierInvoice::where('status', 'draft')->count()],
            ['label' => 'Supplier payments', 'route' => 'accounting.ap.payments.index', 'count' => SupplierPayment::where('status', 'draft')->count()],
            ['label' => 'Payment runs', 'route' => 'accounting.ap.payment-runs.index', 'count' => PaymentRun::where('status', 'draft')->count()],
            ['label' => 'Credit notes', 'route' => 'accounting.ar.credit-notes.index', 'count' => CreditNote::where('status', 'draft')->count()],
            ['label' => 'Purchase orders', 'route' => 'accounting.inventory.purchase-orders.index', 'count' => PurchaseOrder::where('status', 'draft')->count()],
            ['label' => 'Sales orders', 'route' => 'accounting.inventory.sales-orders.index', 'count' => SalesOrder::where('status', 'draft')->count()],
            ['label' => 'Stores requisitions', 'route' => 'accounting.inventory.stores-requisitions.index', 'count' => StoresRequisition::where('status', 'draft')->count()],
        ], fn (array $item) => $item['count'] > 0);
    }
}
