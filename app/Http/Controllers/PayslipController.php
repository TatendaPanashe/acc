<?php

namespace App\Http\Controllers;
use App\Models\Employee; // Corrected: Use Employee model
use App\Models\Earning;
use App\Models\Deduction;
use App\Models\Tax;
use App\Models\PayslipRecord; // Optional
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf; // Corrected facade usage
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage; // For saving PDF

class PayslipController extends Controller
{
    /**
     * Display a form to select user and pay period for payslip generation.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = Employee::all(); // Corrected: Fetch Employees
        // Corrected view path to match your provided Blade file structure
        return view('pages.payslips.create', compact('users'));
    }

    /**
     * Generate a payslip PDF for a given user and pay period.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function generatePdf(Request $request)
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id', // Corrected: expecting user_id from form
            'pay_period_start' => 'required|date',
            'pay_period_end'   => 'required|date|after_or_equal:pay_period_start',
            'pay_date'         => 'required|date|after_or_equal:pay_period_end',
        ]);

        $user = Employee::with('grade')->findOrFail($request->user_id); // Corrected: use Employee model and user_id

        $payPeriodStart = Carbon::parse($request->pay_period_start);
        $payPeriodEnd = Carbon::parse($request->pay_period_end);
        $payDate = Carbon::parse($request->pay_date);

        // --- Company Information (Placeholder - pull from config or Company model) ---
        $companyInfo = [
            'name'    => 'Your Company Name (Pvt) Ltd',
            'address' => '123 Business Park, City, Country',
            'phone'   => '+123 456 7890',
            'email'   => 'info@yourcompany.com',
            'logo'    => public_path('images/company_logo.png'), // Ensure this path is correct or use a placeholder
        ];

        // --- Employee Information ---
        $employeeInfo = [
            'name'                => $user->name,
            'employee_id'         => $user->employee_id ?? 'N/A',
            'job_title'           => $user->job_title ?? 'N/A',
            'department'          => $user->department ?? 'N/A',
            'bank_name'           => $user->bank_name ?? 'N/A',
            'bank_account_number' => $user->bank_account_number ?? 'N/A',
            'tax_id_number'       => $user->tax_id_number ?? 'N/A',
        ];
        // Removed dd($employeeInfo);

        // --- Earnings Calculation ---
        $basicSalary = $user->grade->basic_salary ?? 0; // Get basic salary from user's grade
        $grossEarnings = $basicSalary;
        $earningsDetails = [
            ['name' => 'Basic Salary', 'amount' => $basicSalary],
        ];

        // Fetch other earnings for the user within the pay period
        $otherEarnings = Earning::where('user_id', $user->id)
                                ->activeDuringPeriod($payPeriodStart, $payPeriodEnd)
                                ->where('type', '!=', 'basic_salary') // Exclude basic if handled by grade
                                ->get();

        foreach ($otherEarnings as $earning) {
            $calculatedEarning = $earning->is_percentage ? ($basicSalary * $earning->value / 100) : $earning->value;
            $grossEarnings += $calculatedEarning;
            $earningsDetails[] = ['name' => $earning->name, 'amount' => $calculatedEarning];
        }

        // --- Deductions Calculation ---
        $totalDeductions = 0;
        $deductionsDetails = [];

        // Fetch deductions for the user
        $userDeductions = Deduction::where('user_id', $user->id)
                                   // ->active() // Assuming 'active' status means it applies
                                    ->get();

        foreach ($userDeductions as $deduction) {
            $calculatedDeduction = $deduction->calculateDeduction($grossEarnings); // Use the helper method
            $totalDeductions += $calculatedDeduction;
            $deductionsDetails[] = ['name' => $deduction->name, 'amount' => $calculatedDeduction];
        }

        // --- Tax Calculation (Simplified PAYE Example) ---
        // You'll need to implement your specific tax logic here.
        // For demonstration, a flat rate or simple progressive tax.
        $payeTax = 0;
        $incomeTax = Tax::getActiveTax('income_tax', $payDate); // Get active income tax rules

        if ($incomeTax) {
            $payeTax = $incomeTax->calculateTax($grossEarnings); // Use the tax model's calculator
        } else {
            // Fallback or warning if no tax rules found
            // $payeTax = $grossEarnings * 0.15; // Example flat 15% if no rules
        }
        $totalDeductions += $payeTax;
        $deductionsDetails[] = ['name' => 'PAYE Tax', 'amount' => $payeTax];

        // --- Net Pay Calculation ---
        $netPay = $grossEarnings - $totalDeductions;

        // --- Year-to-Date (YTD) Calculation ---
        // This is a simplified YTD calculation. In a real system, you'd likely sum up
        // values from previous PayslipRecords or a dedicated payroll history table.
        $fiscalYearStart = Carbon::parse($payPeriodEnd->format('Y') . '-01-01'); // Assuming Jan 1st fiscal year
        if ($payPeriodEnd->month < 1) { // Adjust for fiscal year starting mid-year if needed
            $fiscalYearStart->subYear();
        }

        $ytdGrossEarnings = 0;
        $ytdTotalDeductions = 0;
        $ytdNetPay = 0;
        $ytdTaxPaid = 0;

        // Fetch all earnings for the user within the fiscal year up to payPeriodEnd
        $ytdEarnings = Earning::where('user_id', $user->id)
                               ->where('effective_date', '>=', $fiscalYearStart)
                               ->where('effective_date', '<=', $payPeriodEnd)
                               ->get();

        foreach ($ytdEarnings as $earning) {
            // Ensure basic salary is included in YTD gross earnings if it's not explicitly an 'Earning' record
            if ($earning->type === 'basic_salary') {
                 $calculatedEarning = $user->grade->basic_salary;
            } else {
                $calculatedEarning = $earning->is_percentage ? ($user->grade->basic_salary * $earning->value / 100) : $earning->value;
            }
            $ytdGrossEarnings += $calculatedEarning;
        }

        $ytdDeductions = Deduction::where('user_id', $user->id)
                                   ->where('created_at', '>=', $fiscalYearStart) // Assuming creation date for recurring deductions
                                   ->where('created_at', '<=', $payPeriodEnd)
                                   ->get();

        foreach ($ytdDeductions as $deduction) {
            $ytdTotalDeductions += $deduction->calculateDeduction($ytdGrossEarnings); // Recalculate based on YTD gross
        }

        // YTD Tax - more complex, might need to sum up actual tax paid on previous payslips
        // For simplicity, we'll just add current period's tax to a running total if no historical records are available.
        // A better approach would be to sum 'ytd_tax_paid' from previous PayslipRecords.
        $ytdTaxPaid = PayslipRecord::where('user_id', $user->id)
                                    ->whereBetween('pay_period_end', [$fiscalYearStart, $payPeriodEnd])
                                    ->sum('ytd_tax_paid'); // Summing up previous YTD tax
        $ytdTaxPaid += $payeTax; // Add current period's tax

        $ytdNetPay = $ytdGrossEarnings - $ytdTotalDeductions - $ytdTaxPaid;


        // --- Compile Payslip Data ---
        $payslipData = [
            'company'    => $companyInfo,
            'employee'   => $employeeInfo,
            'pay_period' => [
                'start_date' => $payPeriodStart->format('Y-m-d'),
                'end_date'   => $payPeriodEnd->format('Y-m-d'),
                'pay_date'   => $payDate->format('Y-m-d'),
            ],
            'currency'          => $user->currency ?? 'USD', // Assuming user has a currency or default
            'earnings'          => $earningsDetails,
            'gross_earnings'    => $grossEarnings,
            'deductions'        => $deductionsDetails,
            'total_deductions'  => $totalDeductions,
            'net_pay'           => $netPay,
            'ytd'               => [
                'gross_earnings'   => $ytdGrossEarnings,
                'total_deductions' => $ytdTotalDeductions,
                'net_pay'          => $ytdNetPay,
                'tax_paid'         => $ytdTaxPaid,
            ],
        ];

        // --- Store Payslip Record (Optional) ---
        // This creates a historical record of the generated payslip
        PayslipRecord::create([
            'user_id'              => $user->id, // Corrected from 'id' to 'user_id'
            'pay_period_start'     => $payPeriodStart,
            'pay_period_end'       => $payPeriodEnd,
            'pay_date'             => $payDate,
            'gross_earnings'       => $grossEarnings,
            'total_deductions'     => $totalDeductions,
            'net_pay'              => $netPay,
            'ytd_gross_earnings'   => $ytdGrossEarnings,
            'ytd_total_deductions' => $ytdTotalDeductions,
            'ytd_net_pay'          => $ytdNetPay,
            'ytd_tax_paid'         => $ytdTaxPaid,
            'currency'             => $payslipData['currency'],
            'data_snapshot'        => $payslipData, // Store a full snapshot for immutability
        ]);


        // --- Generate PDF ---
        $html = View::make('payslips.pdf', compact('payslipData'))->render();

        $pdf = PDF::loadHTML($html);

        // Set PDF options (optional)
        $pdf->setOptions([
            'margin-top'    => 10,
            'margin-right'  => 10,
            'margin-bottom' => 10,
            'margin-left'   => 10,
            'defaultFont'   => 'sans-serif', // Ensure a common font is used
        ]);

        $filename = 'payslip_' . ($user->employee_id ?? $user->id) . '_' . $payPeriodEnd->format('Y-m') . '.pdf';

        return $pdf->download($filename);
    }
}
