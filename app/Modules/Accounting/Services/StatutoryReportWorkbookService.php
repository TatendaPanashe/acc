<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\SalesInvoice;
use App\Modules\Accounting\Models\SupplierInvoice;
use Illuminate\Support\Collection;

class StatutoryReportWorkbookService
{
    public function __construct(
        private readonly TrialBalanceService $trialBalanceService,
        private readonly FinancialStatementService $statements,
    ) {
    }

    public function reports(): array
    {
        return [
            'statutory-pack' => 'Full Statutory Pack',
            'trial-balance' => 'Trial Balance',
            'profit-and-loss' => 'Profit & Loss',
            'balance-sheet' => 'Balance Sheet',
            'sales-vat' => 'Sales VAT Output Tax',
            'purchases-vat' => 'Purchases VAT Input Tax',
            'receivables-aging' => 'Receivables Aging',
            'payables-aging' => 'Payables Aging',
        ];
    }

    public function workbook(string $report, ?AccountingPeriod $period = null): string
    {
        $sheets = $report === 'statutory-pack'
            ? [
                $this->trialBalance($period),
                $this->profitAndLoss($period),
                $this->balanceSheet($period),
                $this->salesVat(),
                $this->purchasesVat(),
                $this->receivablesAging(),
                $this->payablesAging(),
            ]
            : [$this->sheet($report, $period)];

        return $this->xml($sheets);
    }

    public function filename(string $report): string
    {
        return 'acc263-'.str_replace('_', '-', $report).'-'.now()->format('Ymd-His').'.xls';
    }

    private function sheet(string $report, ?AccountingPeriod $period): array
    {
        return match ($report) {
            'trial-balance' => $this->trialBalance($period),
            'profit-and-loss' => $this->profitAndLoss($period),
            'balance-sheet' => $this->balanceSheet($period),
            'sales-vat' => $this->salesVat(),
            'purchases-vat' => $this->purchasesVat(),
            'receivables-aging' => $this->receivablesAging(),
            'payables-aging' => $this->payablesAging(),
            default => abort(404),
        };
    }

    private function trialBalance(?AccountingPeriod $period): array
    {
        $rows = $this->trialBalanceService->forPeriod($period);

        return [
            'name' => 'Trial Balance',
            'title' => 'Trial Balance',
            'subtitle' => $this->periodLabel($period),
            'headers' => ['Account Code', 'Account Name', 'Type', 'Debit', 'Credit', 'Balance'],
            'rows' => $rows->map(fn ($account) => [
                $account->code,
                $account->name,
                ucfirst($account->type),
                $account->debit_total,
                $account->credit_total,
                $account->balance,
            ])->push([
                '', 'Totals', '', $rows->sum('debit_total'), $rows->sum('credit_total'), $rows->sum('balance'),
            ])->all(),
        ];
    }

    private function profitAndLoss(?AccountingPeriod $period): array
    {
        $window = $this->statements->window($period);
        $report = $this->statements->profitAndLoss($window['from'], $window['to']);

        return [
            'name' => 'Profit and Loss',
            'title' => 'Profit & Loss',
            'subtitle' => $window['label'].' ('.($window['from']?->format('Y-m-d') ?? 'beginning').' to '.$window['to']->format('Y-m-d').')',
            'headers' => ['Section', 'Account Code', 'Account Name', 'Amount'],
            'rows' => $report['revenue']->map(fn ($account) => ['Revenue', $account->code, $account->name, $account->balance])
                ->push(['', '', 'Total Revenue', $report['revenue_total']])
                ->concat($report['expenses']->map(fn ($account) => ['Expense', $account->code, $account->name, $account->balance]))
                ->push(['', '', 'Total Expenses', $report['expense_total']])
                ->push(['', '', 'Net Profit / Loss', $report['net']])
                ->all(),
        ];
    }

    private function balanceSheet(?AccountingPeriod $period): array
    {
        $report = $this->statements->balanceSheet($this->statements->asAt($period));
        $rows = collect();

        foreach (['assets' => 'Asset', 'liabilities' => 'Liability', 'equity' => 'Equity'] as $key => $label) {
            $rows = $rows->concat($report[$key]->map(fn ($account) => [$label, $account->code, $account->name, $account->balance]));
            if ($key === 'equity') {
                $rows->push(['Equity', '', 'Retained earnings from prior years', $report['prior_year_earnings']]);
                $rows->push(['Equity', '', 'Current year profit / (loss)', $report['current_year_earnings']]);
            }
            $rows->push(['', '', 'Total '.ucfirst($key), $report[match ($key) { 'assets' => 'asset_total', 'liabilities' => 'liability_total', default => 'equity_total' }]]);
        }

        return [
            'name' => 'Balance Sheet',
            'title' => 'Balance Sheet',
            'subtitle' => 'As at '.$report['as_at']->format('Y-m-d'),
            'headers' => ['Section', 'Account Code', 'Account Name', 'Amount'],
            'rows' => $rows->push(['', '', 'Assets less Liabilities and Equity', $report['difference']])->all(),
        ];
    }

    private function salesVat(): array
    {
        $invoices = SalesInvoice::with('customer')
            ->whereIn('status', ['posted', 'part_paid', 'paid'])
            ->orderBy('invoice_date')
            ->get();

        return [
            'name' => 'Sales VAT',
            'title' => 'Sales VAT Output Tax',
            'subtitle' => 'Generated '.now()->format('Y-m-d'),
            'headers' => ['Invoice', 'Date', 'Customer', 'Currency', 'Subtotal', 'Tax', 'Total', 'Status'],
            'rows' => $invoices->map(fn (SalesInvoice $invoice) => [
                $invoice->invoice_number,
                $invoice->invoice_date?->format('Y-m-d'),
                $invoice->customer?->name,
                $invoice->currency,
                $invoice->subtotal,
                $invoice->tax_total,
                $invoice->total,
                ucfirst(str_replace('_', ' ', $invoice->status)),
            ])->push(['', '', 'Totals', '', $invoices->sum('subtotal'), $invoices->sum('tax_total'), $invoices->sum('total'), ''])->all(),
        ];
    }

    private function purchasesVat(): array
    {
        $invoices = SupplierInvoice::with('supplier')
            ->whereIn('status', ['posted', 'part_paid', 'paid'])
            ->orderBy('invoice_date')
            ->get();

        return [
            'name' => 'Purchases VAT',
            'title' => 'Purchases VAT Input Tax',
            'subtitle' => 'Generated '.now()->format('Y-m-d'),
            'headers' => ['Invoice', 'Date', 'Supplier', 'Currency', 'Subtotal', 'Tax', 'Total', 'Status'],
            'rows' => $invoices->map(fn (SupplierInvoice $invoice) => [
                $invoice->invoice_number,
                $invoice->invoice_date?->format('Y-m-d'),
                $invoice->supplier?->name,
                $invoice->currency,
                $invoice->subtotal,
                $invoice->tax_total,
                $invoice->total,
                ucfirst(str_replace('_', ' ', $invoice->status)),
            ])->push(['', '', 'Totals', '', $invoices->sum('subtotal'), $invoices->sum('tax_total'), $invoices->sum('total'), ''])->all(),
        ];
    }

    private function receivablesAging(): array
    {
        return $this->agingSheet('Receivables Aging', SalesInvoice::with('customer')->whereIn('status', ['posted', 'part_paid'])->orderBy('due_date')->get(), 'customer');
    }

    private function payablesAging(): array
    {
        return $this->agingSheet('Payables Aging', SupplierInvoice::with('supplier')->whereIn('status', ['posted', 'part_paid'])->orderBy('due_date')->get(), 'supplier');
    }

    private function agingSheet(string $title, Collection $invoices, string $partyRelation): array
    {
        $asOf = now()->startOfDay();
        $rows = $invoices
            ->filter(fn ($invoice) => $invoice->balance > 0)
            ->map(function ($invoice) use ($asOf, $partyRelation) {
                $days = $invoice->due_date->lt($asOf) ? (int) $invoice->due_date->diffInDays($asOf) : 0;

                return [
                    $invoice->invoice_number,
                    $invoice->due_date?->format('Y-m-d'),
                    $invoice->{$partyRelation}?->name,
                    $invoice->currency,
                    $invoice->total,
                    $invoice->amount_paid,
                    $invoice->balance,
                    $days,
                    $this->bucket($days),
                ];
            });

        return [
            'name' => $title,
            'title' => $title,
            'subtitle' => 'As of '.$asOf->format('Y-m-d'),
            'headers' => ['Invoice', 'Due Date', 'Party', 'Currency', 'Total', 'Paid', 'Balance', 'Days Overdue', 'Bucket'],
            'rows' => $rows->push(['', '', 'Total', '', '', '', $rows->sum(6), '', ''])->all(),
        ];
    }

    private function xml(array $sheets): string
    {
        $xml = '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles><Style ss:ID="Default" ss:Name="Normal"><Protection ss:Protected="1"/></Style><Style ss:ID="Brand"><Font ss:Bold="1" ss:Size="18" ss:Color="#1957C2"/></Style><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="13"/></Style><Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#172033" ss:Pattern="Solid"/></Style><Style ss:ID="Money"><NumberFormat ss:Format="#,##0.00"/></Style></Styles>';

        foreach ($sheets as $sheet) {
            $xml .= '<Worksheet ss:Name="'.$this->escape($sheet['name']).'"><Table>';
            $xml .= '<Row><Cell ss:MergeAcross="'.max(0, count($sheet['headers']) - 1).'" ss:StyleID="Brand"><Data ss:Type="String">acc263</Data></Cell></Row>';
            $xml .= '<Row><Cell ss:MergeAcross="'.max(0, count($sheet['headers']) - 1).'" ss:StyleID="Title"><Data ss:Type="String">'.$this->escape($sheet['title']).'</Data></Cell></Row>';
            $xml .= '<Row><Cell ss:MergeAcross="'.max(0, count($sheet['headers']) - 1).'"><Data ss:Type="String">'.$this->escape($sheet['subtitle']).'</Data></Cell></Row>';
            $xml .= '<Row/>';
            $xml .= '<Row>';
            foreach ($sheet['headers'] as $header) {
                $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">'.$this->escape($header).'</Data></Cell>';
            }
            $xml .= '</Row>';

            foreach ($sheet['rows'] as $row) {
                $xml .= '<Row>';
                foreach ($row as $value) {
                    $isNumeric = is_int($value) || is_float($value);
                    $xml .= '<Cell'.($isNumeric ? ' ss:StyleID="Money"' : '').'><Data ss:Type="'.($isNumeric ? 'Number' : 'String').'">'.$this->escape((string) $value).'</Data></Cell>';
                }
                $xml .= '</Row>';
            }

            $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><ProtectObjects>True</ProtectObjects><ProtectScenarios>True</ProtectScenarios><EnableSelection>NoSelection</EnableSelection></WorksheetOptions></Worksheet>';
        }

        return $xml.'</Workbook>';
    }

    private function periodLabel(?AccountingPeriod $period): string
    {
        return $period ? $period->name.' ('.$period->starts_on?->format('Y-m-d').' to '.$period->ends_on?->format('Y-m-d').')' : 'All posted periods';
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days === 0 => 'Current',
            $days <= 30 => '1-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
