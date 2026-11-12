<!DOCTYPE html>
<html>
<head>
    <title>Payslip - {{ $payslipData['employee']['name'] }}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif; /* Using Inter as requested for general use */
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            background-color: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #007bff;
        }
        .header p {
            margin: 2px 0;
            font-size: 11px;
        }
        .header .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 10px;
            border-radius: 8px;
        }
        .section-title {
            background-color: #f2f2f2;
            padding: 8px 10px;
            margin-top: 15px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 11px;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .details-grid {
            display: table; /* Use table-like display for PDF consistency */
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .details-grid .row {
            display: table-row;
        }
        .details-grid .col {
            display: table-cell;
            padding: 5px 10px;
            vertical-align: top;
            width: 50%; /* Two columns */
        }
        .details-grid .col strong {
            display: inline-block;
            width: 100px; /* Align labels */
            font-weight: normal;
            color: #555;
        }
        .details-grid .col span {
            font-weight: bold;
            color: #333;
        }
        .table-payslip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table-payslip th, .table-payslip td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
            font-size: 10px;
        }
        .table-payslip th {
            background-color: #f8f8f8;
            font-weight: bold;
            color: #555;
        }
        .table-payslip .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #e9f5ff; /* Light blue background for totals */
        }
        .net-pay-section {
            text-align: right;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #007bff;
        }
        .net-pay-section h2 {
            font-size: 18px;
            color: #007bff;
            margin: 0;
        }
        .net-pay-section span {
            font-size: 22px;
            font-weight: bold;
            color: #28a745; /* Green for net pay */
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
            font-size: 9px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if(isset($payslipData['company']['logo']) && file_exists($payslipData['company']['logo']))
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($payslipData['company']['logo'])) }}" alt="Company Logo" class="logo">
            @else
                <img src="[https://placehold.co/120x60/007bff/ffffff?text=Company+Logo](https://placehold.co/120x60/007bff/ffffff?text=Company+Logo)" alt="Placeholder Logo" class="logo">
            @endif
            <h1>{{ $payslipData['company']['name'] }}</h1>
            <p>{{ $payslipData['company']['address'] }}</p>
            <p>Phone: {{ $payslipData['company']['phone'] }} | Email: {{ $payslipData['company']['email'] }}</p>
            <p><strong>Payslip for Period: {{ $payslipData['pay_period']['start_date'] }} to {{ $payslipData['pay_period']['end_date'] }}</strong></p>
            <p><strong>Pay Date: {{ $payslipData['pay_period']['pay_date'] }}</strong></p>
        </div>

        <div class="section-title">Employee Details</div>
        <div class="details-grid">
            <div class="row">
                <div class="col"><strong>Employee Name:</strong> <span>{{ $payslipData['employee']['name'] }}</span></div>
                <div class="col"><strong>Employee ID:</strong> <span>{{ $payslipData['employee']['employee_id'] }}</span></div>
            </div>
            <div class="row">
                <div class="col"><strong>Job Title:</strong> <span>{{ $payslipData['employee']['job_title'] }}</span></div>
                <div class="col"><strong>Department:</strong> <span>{{ $payslipData['employee']['department'] }}</span></div>
            </div>
            <div class="row">
                <div class="col"><strong>Bank Name:</strong> <span>{{ $payslipData['employee']['bank_name'] }}</span></div>
                <div class="col"><strong>Bank Account:</strong> <span>{{ $payslipData['employee']['bank_account_number'] }}</span></div>
            </div>
            <div class="row">
                <div class="col"><strong>Tax ID Number:</strong> <span>{{ $payslipData['employee']['tax_id_number'] }}</span></div>
                <div class="col"></div>
            </div>
        </div>

        <div class="section-title">Earnings</div>
        <table class="table-payslip">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Current Period ({{ $payslipData['currency'] }})</th>
                    <th class="text-right">Year-to-Date ({{ $payslipData['currency'] }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payslipData['earnings'] as $earning)
                    <tr>
                        <td>{{ $earning['name'] }}</td>
                        <td class="text-right">{{ number_format($earning['amount'], 2) }}</td>
                        <td class="text-right">N/A</td> {{-- YTD for individual earnings is complex, placeholder --}}
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Gross Earnings</strong></td>
                    <td class="text-right"><strong>{{ number_format($payslipData['gross_earnings'], 2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($payslipData['ytd']['gross_earnings'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Deductions</div>
        <table class="table-payslip">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Current Period ({{ $payslipData['currency'] }})</th>
                    <th class="text-right">Year-to-Date ({{ $payslipData['currency'] }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payslipData['deductions'] as $deduction)
                    <tr>
                        <td>{{ $deduction['name'] }}</td>
                        <td class="text-right">{{ number_format($deduction['amount'], 2) }}</td>
                        <td class="text-right">N/A</td> {{-- YTD for individual deductions is complex, placeholder --}}
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td><strong>Total Deductions</strong></td>
                    <td class="text-right"><strong>{{ number_format($payslipData['total_deductions'], 2) }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($payslipData['ytd']['total_deductions'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Summary</div>
        <table class="table-payslip">
            <tbody>
                <tr class="total-row">
                    <td><strong>Gross Earnings</strong></td>
                    <td class="text-right"><strong>{{ $payslipData['currency'] }} {{ number_format($payslipData['gross_earnings'], 2) }}</strong></td>
                    <td class="text-right"><strong>YTD: {{ $payslipData['currency'] }} {{ number_format($payslipData['ytd']['gross_earnings'], 2) }}</strong></td>
                </tr>
                <tr class="total-row">
                    <td><strong>Total Deductions</strong></td>
                    <td class="text-right"><strong>{{ $payslipData['currency'] }} {{ number_format($payslipData['total_deductions'], 2) }}</strong></td>
                    <td class="text-right"><strong>YTD: {{ $payslipData['currency'] }} {{ number_format($payslipData['ytd']['total_deductions'], 2) }}</strong></td>
                </tr>
                <tr class="total-row">
                    <td><strong>Tax Paid (PAYE)</strong></td>
                    <td class="text-right"><strong>{{ $payslipData['currency'] }} {{ number_format($payslipData['deductions'][array_search('PAYE Tax', array_column($payslipData['deductions'], 'name'))]['amount'] ?? 0, 2) }}</strong></td>
                    <td class="text-right"><strong>YTD: {{ $payslipData['currency'] }} {{ number_format($payslipData['ytd']['tax_paid'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="net-pay-section">
            <h2>Net Pay: <span>{{ $payslipData['currency'] }} {{ number_format($payslipData['net_pay'], 2) }}</span></h2>
            <p>Year-to-Date Net Pay: {{ $payslipData['currency'] }} {{ number_format($payslipData['ytd']['net_pay'], 2) }}</p>
        </div>

        <div class="footer">
            <p>This is a computer generated payslip and does not require a signature.</p>
            <p>&copy; {{ date('Y') }} {{ $payslipData['company']['name'] }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
