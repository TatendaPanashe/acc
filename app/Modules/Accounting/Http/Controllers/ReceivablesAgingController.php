<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\SalesInvoice;
use Illuminate\View\View;

class ReceivablesAgingController extends Controller
{
    public function __invoke(): View
    {
        $asOf = now()->startOfDay();
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];

        $rows = SalesInvoice::with('customer')
            ->whereIn('status', ['posted', 'part_paid'])
            ->orderBy('due_date')
            ->get()
            ->filter(fn (SalesInvoice $invoice) => $invoice->balance > 0)
            ->map(function (SalesInvoice $invoice) use ($asOf, &$buckets) {
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
                    'dunning_level' => $this->dunningLevel($daysOverdue),
                ];
            });

        return view('accounting.ar.aging.index', [
            'rows' => $rows,
            'buckets' => $buckets,
            'total' => array_sum($buckets),
            'asOf' => $asOf,
        ]);
    }

    private function dunningLevel(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue === 0 => 'Not due',
            $daysOverdue <= 30 => 'Reminder',
            $daysOverdue <= 60 => 'Second notice',
            $daysOverdue <= 90 => 'Final notice',
            default => 'Credit hold review',
        };
    }
}
