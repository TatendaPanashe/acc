<?php

namespace App\Modules\Accounting\Services\Approvals;

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\PaymentRun;
use App\Modules\Accounting\Models\PurchaseOrder;
use App\Modules\Accounting\Models\SalesOrder;
use App\Modules\Accounting\Models\StoresRequisition;
use App\Modules\Accounting\Models\SupplierInvoice;
use App\Modules\Accounting\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Model;

/**
 * Every document that needs approval before it is finalised: the route that approves it,
 * the route parameter holding the document, and how to read its number, date and amount.
 */
class ApprovalDocuments
{
    public const TYPES = [
        'journal' => [
            'label' => 'Journal entry', 'model' => JournalEntry::class, 'route' => 'accounting.gl.journals.post', 'param' => 'journal',
            'number' => 'entry_number', 'date' => 'entry_date', 'amount' => null, 'show' => 'accounting.gl.journals.show', 'action' => 'Post',
        ],
        'supplier_invoice' => [
            'label' => 'Supplier invoice', 'model' => SupplierInvoice::class, 'route' => 'accounting.ap.supplier-invoices.post', 'param' => 'supplierInvoice',
            'number' => 'invoice_number', 'date' => 'invoice_date', 'amount' => 'total', 'show' => 'accounting.ap.supplier-invoices.show', 'action' => 'Post',
        ],
        'supplier_payment' => [
            'label' => 'Supplier payment', 'model' => SupplierPayment::class, 'route' => 'accounting.ap.payments.post', 'param' => 'payment',
            'number' => 'payment_number', 'date' => 'payment_date', 'amount' => 'amount', 'show' => null, 'index' => 'accounting.ap.payments.index', 'action' => 'Post',
        ],
        'payment_run' => [
            'label' => 'Payment run', 'model' => PaymentRun::class, 'route' => 'accounting.ap.payment-runs.post', 'param' => 'paymentRun',
            'number' => 'run_number', 'date' => 'payment_date', 'amount' => 'total_amount', 'show' => 'accounting.ap.payment-runs.show', 'action' => 'Post',
        ],
        'credit_note' => [
            'label' => 'Credit note', 'model' => CreditNote::class, 'route' => 'accounting.ar.credit-notes.post', 'param' => 'creditNote',
            'number' => 'credit_note_number', 'date' => 'credit_note_date', 'amount' => 'total', 'show' => null, 'index' => 'accounting.ar.credit-notes.index', 'action' => 'Post',
        ],
        'purchase_order' => [
            'label' => 'Purchase order', 'model' => PurchaseOrder::class, 'route' => 'accounting.inventory.purchase-orders.approve', 'param' => 'purchaseOrder',
            'number' => 'order_number', 'date' => 'order_date', 'amount' => 'total', 'show' => 'accounting.inventory.purchase-orders.show', 'action' => 'Approve',
        ],
        'sales_order' => [
            'label' => 'Sales order', 'model' => SalesOrder::class, 'route' => 'accounting.inventory.sales-orders.confirm', 'param' => 'salesOrder',
            'number' => 'order_number', 'date' => 'order_date', 'amount' => 'total', 'show' => 'accounting.inventory.sales-orders.show', 'action' => 'Confirm',
        ],
        'stores_requisition' => [
            'label' => 'Stores requisition', 'model' => StoresRequisition::class, 'route' => 'accounting.inventory.stores-requisitions.approve', 'param' => 'requisition',
            'number' => 'requisition_number', 'date' => 'required_date', 'amount' => null, 'show' => 'accounting.inventory.stores-requisitions.show', 'action' => 'Approve',
        ],
    ];

    public static function forRoute(?string $routeName): ?string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($definition['route'] === $routeName) {
                return $type;
            }
        }

        return null;
    }

    public static function typeOf(Model $model): ?string
    {
        foreach (self::TYPES as $type => $definition) {
            if ($model instanceof $definition['model']) {
                return $type;
            }
        }

        return null;
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /** Document amount in its own currency. */
    public static function amount(string $type, Model $model): ?float
    {
        if ($type === 'journal') {
            return round((float) $model->lines()->sum('debit'), 2);
        }
        if ($type === 'stores_requisition') {
            return null;
        }

        return round((float) $model->{self::TYPES[$type]['amount']}, 2);
    }

    public static function number(string $type, Model $model): string
    {
        return (string) ($model->{self::TYPES[$type]['number']} ?? '#'.$model->getKey());
    }

    public static function url(string $type, Model $model): ?string
    {
        $definition = self::TYPES[$type];

        return $definition['show'] ? route($definition['show'], $model) : (isset($definition['index']) ? route($definition['index']) : null);
    }

    /** Draft documents awaiting approval, newest first. */
    public static function pending(string $type)
    {
        $definition = self::TYPES[$type];

        return $definition['model']::query()->where('status', 'draft')->latest('id');
    }
}
