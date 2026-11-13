<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip {{ $employee->employee_number }} {{ $run->run_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; margin: 0; }
        h1 { font-size: 16px; margin: 0; } h2 { font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #333; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; } td, th { padding: 3px 4px; vertical-align: top; }
        th { text-align: left; color: #444; font-weight: bold; } .r { text-align: right; } .muted { color: #555; }
        .box { border: 1px solid #ccc; padding: 8px; } .total td { border-top: 1px solid #333; font-weight: bold; }
        .net { font-size: 13px; font-weight: bold; background: #f1f4f9; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td><h1>{{ $company?->name }}</h1><div class="muted">{{ $company?->address ?? '' }}</div></td>
            <td class="r"><h1>PAYSLIP</h1><div>{{ $run->period_start->format('d M') }} – {{ $run->period_end->format('d M Y') }}</div><div class="muted">Pay date {{ $run->pay_date->format('d M Y') }} · {{ $run->run_number }}</div></td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px">
        <table>
            <tr><th>Employee</th><td>{{ $employee->name }} ({{ $employee->employee_number }})</td><th>Job</th><td>{{ $employee->job_title }}{{ $employee->department ? ', '.$employee->department : '' }}</td></tr>
            <tr><th>TIN</th><td>{{ $employee->tin ?: '—' }}</td><th>NSSA number</th><td>{{ $employee->nssa_number ?: '—' }}</td></tr>
            <tr><th>Paid by</th><td colspan="3">{{ \App\Modules\Payroll\Models\Employee::PAYMENT_METHODS[$employee->payment_method] ?? '' }} {{ $employee->payment_method === 'mobile_money' ? $employee->mobile_wallet : trim($employee->bank_name.' '.$employee->account_number) }}</td></tr>
        </table>
    </div>

    @foreach($slips as $slip)
        @php $b = $slip->breakdown ?? []; @endphp
        <h2>Pay in {{ $slip->currency }}{{ count($slips) > 1 ? ' ('.round(($b['share'] ?? 1) * 100, 2).'% of pay'.(($b['fx_rate'] ?? 1) != 1 ? ', rate '.rtrim(rtrim(number_format($b['fx_rate'], 6), '0'), '.') : '').')' : '' }}</h2>
        <table>
            <tr>
                <td style="width:50%">
                    <table>
                        <tr><th>Earnings</th><th class="r">{{ $slip->currency }}</th></tr>
                        @foreach($b['earnings'] ?? [] as $line)<tr><td>{{ $line['name'] }}{{ $line['quantity'] ? ' ('.$line['quantity'].' h)' : '' }}{{ !$line['taxable'] ? ' · non-taxable' : '' }}</td><td class="r">{{ number_format($line['amount'], 2) }}</td></tr>@endforeach
                        <tr class="total"><td>Gross pay</td><td class="r">{{ number_format((float) $slip->gross, 2) }}</td></tr>
                    </table>
                </td>
                <td style="width:50%">
                    <table>
                        <tr><th>Deductions</th><th class="r">{{ $slip->currency }}</th></tr>
                        <tr><td>PAYE</td><td class="r">{{ number_format((float) $slip->paye, 2) }}</td></tr>
                        <tr><td>AIDS levy</td><td class="r">{{ number_format((float) $slip->aids_levy, 2) }}</td></tr>
                        <tr><td>NSSA</td><td class="r">{{ number_format((float) $slip->nssa_employee, 2) }}</td></tr>
                        @foreach($b['deductions'] ?? [] as $line)<tr><td>{{ $line['name'] }}</td><td class="r">{{ number_format($line['amount'], 2) }}</td></tr>@endforeach
                        @foreach(collect($b['levies'] ?? [])->where('payer', 'employee') as $levy)<tr><td>{{ $levy['name'] }}</td><td class="r">{{ number_format($levy['amount'], 2) }}</td></tr>@endforeach
                        <tr class="total"><td>Total deductions</td><td class="r">{{ number_format((float) $slip->paye + (float) $slip->aids_levy + (float) $slip->nssa_employee + (float) $slip->other_deductions, 2) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <table style="margin-top:6px"><tr class="net"><td style="padding:6px">Net pay ({{ $slip->currency }})</td><td class="r" style="padding:6px">{{ number_format((float) $slip->net, 2) }}</td></tr></table>
        <div class="muted" style="margin-top:4px">
            Taxable income {{ number_format((float) $slip->taxable, 2) }}
            @if(!empty($b['band'])) · tax band {{ $b['band']['rate'] }}% less {{ number_format($b['band']['deduct'], 2) }} = {{ number_format($b['paye_before_credits'] ?? 0, 2) }}@endif
            @foreach($b['credits'] ?? [] as $credit) · {{ $credit['name'] }} −{{ number_format($credit['amount'], 2) }}@endforeach
            · Employer NSSA {{ number_format((float) $slip->nssa_employer, 2) }}
        </div>
    @endforeach

    @if($ytd)
        <h2>Year to date ({{ $run->taxYear() }})</h2>
        <table>
            <tr><th>Currency</th><th class="r">Gross</th><th class="r">Taxable</th><th class="r">PAYE</th><th class="r">AIDS levy</th><th class="r">NSSA</th><th class="r">Net</th></tr>
            @foreach($ytd as $currency => $t)<tr><td>{{ $currency }}</td><td class="r">{{ number_format($t['gross'], 2) }}</td><td class="r">{{ number_format($t['taxable'], 2) }}</td><td class="r">{{ number_format($t['paye'], 2) }}</td><td class="r">{{ number_format($t['aids_levy'], 2) }}</td><td class="r">{{ number_format($t['nssa_employee'], 2) }}</td><td class="r">{{ number_format($t['net'], 2) }}</td></tr>@endforeach
        </table>
    @endif
    <p class="muted" style="margin-top:14px">{{ $run->status === 'draft' ? 'DRAFT: this payroll has not been approved.' : 'Generated by acc263 on '.now()->format('d M Y').'.' }}</p>
</body>
</html>
