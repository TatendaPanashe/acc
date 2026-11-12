<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\SupplierInvoice;
use Illuminate\View\View;

class PayablesAgingController extends Controller
{
    public function __invoke(): View
    {
        $asOf = now()->startOfDay();
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];

        $rows = SupplierInvoice::with('supplier')
            ->whereIn('status', ['posted', 'part_paid'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (SupplierInvoice $invoice) => $invoice->balance > 0)
            ->map(function (SupplierInvoice $invoice) use ($asOf, &$buckets) {
                $daysOverdue = $invoice->due_date->lt($asOf) ? (int) $invoice->due_date->diffInDays($asOf) : 0;
                $bucket = match (true) {
                    $daysOverdue === 0 => 'current',
                    $daysOverdue <= 30 => '1_30',
                    $daysOverdue <= 60 => '31_60',
                    $daysOverdue <= 90 => '61_90',
                    default => '90_plus',
                };
                $buckets[$bucket] += (float) $invoice->balance;

                return [
                    'invoice' => $invoice,
                    'days_overdue' => $daysOverdue,
                    'bucket' => $bucket,
                    'payment_priority' => $this->priority($daysOverdue),
                ];
            });

        return view('accounting.ap.aging.index', [
            'rows' => $rows,
            'buckets' => $buckets,
            'total' => array_sum($buckets),
            'asOf' => $asOf,
        ]);
    }

    private function priority(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue === 0 => 'Scheduled',
            $daysOverdue <= 30 => 'Due soon',
            $daysOverdue <= 60 => 'Overdue',
            $daysOverdue <= 90 => 'Urgent',
            default => 'Escalate',
        };
    }
}
