<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Services\ExchangeRateService;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeItem;
use App\Modules\Payroll\Models\PayItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\RunInput;
use App\Modules\Payroll\Models\StatutorySetting;
use App\Modules\Payroll\Services\PayrollRunService;
use App\Rules\TenantExists;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Employees, pay items, statutory settings, payroll runs, payslips and schedules. */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollRunService $runs)
    {
    }

    public function index(): View
    {
        $year = now()->year;
        $active = Employee::where('status', 'active')->get();
        $currencies = $active->map(fn ($e) => \App\Modules\Payroll\Services\PayrollCalculator::taxCurrency($e))->unique()->values();
        $settings = StatutorySetting::withCount('bands')->where('tax_year', $year)->get()->keyBy('currency');
        $last = PayrollRun::whereIn('status', ['approved', 'paid'])->latest('period_end')->first();

        return view('payroll.index', [
            'metrics' => [
                ['Active employees', $active->count(), 'users', 'col-blue'],
                ['Last payroll', $last?->run_number ?? '—', 'calendar', 'col-green', $last ? $last->period_end->format('F Y') : null],
                ['Last payroll cost', $last ? number_format((float) $last->total_cost_base, 2) : '—', 'dollar-sign', 'col-purple', 'base currency, incl. employer contributions'],
                ['Runs awaiting approval', PayrollRun::where('status', 'draft')->whereNotNull('calculated_at')->count(), 'clock', 'col-orange'],
            ],
            'checklist' => [
                ['Add employees', $active->isNotEmpty(), route('accounting.payroll.employees.create')],
                ['Set up pay items (allowances, pension, medical aid...)', PayItem::exists(), route('accounting.payroll.items.index')],
                ['Enter '.$year.' statutory settings and PAYE tables'.($currencies->isNotEmpty() ? ' for '.$currencies->join(', ') : ''),
                    $currencies->isNotEmpty() && $currencies->every(fn ($c) => ($settings[$c]->bands_count ?? 0) > 0), route('accounting.payroll.settings.index')],
            ],
            'runs' => PayrollRun::latest('period_end')->latest('id')->limit(8)->get(),
        ]);
    }

    // ---- employees -----------------------------------------------------------------

    public function employees(Request $request): View
    {
        return view('payroll.employees.index', [
            'employees' => Employee::when($request->query('status', 'active') !== 'all', fn ($q) => $q->where('status', $request->query('status', 'active')))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")))
                ->orderBy('employee_number')->paginate(30)->withQueryString(),
            'status' => $request->query('status', 'active'),
        ]);
    }

    public function createEmployee(): View
    {
        $base = app(ExchangeRateService::class)->baseCurrency(auth()->user()->company_id);
        $last = Employee::orderByDesc('id')->value('employee_number');

        return view('payroll.employees.form', $this->employeeFormData(new Employee([
            'employee_number' => 'EMP'.str_pad((string) ((int) preg_replace('/\D/', '', (string) $last) + 1), 4, '0', STR_PAD_LEFT),
            'salary_currency' => $base, 'start_date' => today(), 'payment_method' => 'bank', 'secondary_percent' => 0, 'status' => 'active',
        ])));
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $employee = Employee::create($this->validatedEmployee($request));

        return redirect()->route('accounting.payroll.employees.show', $employee)->with('success', $employee->name.' added. Add their standing allowances and deductions below.');
    }

    public function showEmployee(Employee $employee): View
    {
        return view('payroll.employees.show', [
            'employee' => $employee->load('items.payItem'),
            'payItems' => PayItem::where('status', true)->orderBy('type')->orderBy('name')->get(),
            'payslips' => $employee->payslips()->with('run')->latest('id')->limit(24)->get(),
            'credits' => $this->creditOptions(),
        ]);
    }

    public function editEmployee(Employee $employee): View
    {
        return view('payroll.employees.form', $this->employeeFormData($employee));
    }

    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validatedEmployee($request, $employee));

        return redirect()->route('accounting.payroll.employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function storeEmployeeItem(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'pay_item_id' => ['required', new TenantExists('payroll_pay_items')],
            'amount' => ['nullable', 'numeric'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $employee->items()->create($data + ['company_id' => $employee->company_id]);

        return back()->with('success', 'Pay item added to '.$employee->name.'.');
    }

    public function destroyEmployeeItem(Employee $employee, EmployeeItem $item): RedirectResponse
    {
        abort_unless($item->employee_id === $employee->id, 404);
        $item->delete();

        return back()->with('success', 'Pay item removed.');
    }

    private function employeeFormData(Employee $employee): array
    {
        return ['employee' => $employee, 'currencies' => Currency::where('status', true)->orderBy('code')->pluck('code'), 'credits' => $this->creditOptions()];
    }

    /** Credit codes defined in any statutory setting: code => name. */
    private function creditOptions(): Collection
    {
        return StatutorySetting::orderByDesc('tax_year')->get()->flatMap(fn ($s) => collect($s->credits ?? [])->mapWithKeys(fn ($c) => [$c['code'] => $c['name']]))->unique();
    }

    private function validatedEmployee(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'employee_number' => ['required', 'max:40', Rule::unique('payroll_employees')->where('company_id', $request->user()->company_id)->ignore($employee)],
            'first_name' => ['required', 'max:255'], 'last_name' => ['required', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'max:40'],
            'national_id' => ['nullable', 'max:40'], 'tin' => ['nullable', 'max:40'], 'nssa_number' => ['nullable', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'job_title' => ['nullable', 'max:255'], 'department' => ['nullable', 'max:255'], 'cost_centre' => ['nullable', 'max:60'],
            'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'salary_currency' => ['required', 'size:3', TenantExists::currency()],
            'secondary_currency' => ['nullable', 'size:3', 'different:salary_currency', TenantExists::currency()],
            'secondary_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_with:secondary_currency'],
            'payment_method' => ['required', Rule::in(array_keys(Employee::PAYMENT_METHODS))],
            'bank_name' => ['nullable', 'max:255'], 'bank_branch' => ['nullable', 'max:255'], 'account_number' => ['nullable', 'max:60', 'required_if:payment_method,bank'],
            'mobile_wallet' => ['nullable', 'max:40', 'required_if:payment_method,mobile_money'],
            'tax_credits' => ['nullable', 'array'], 'tax_credits.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'max:2000'],
        ]);
        $data['salary_currency'] = strtoupper($data['salary_currency']);
        $data['secondary_currency'] = !empty($data['secondary_currency']) ? strtoupper($data['secondary_currency']) : null;
        $data['secondary_percent'] = $data['secondary_currency'] ? ($data['secondary_percent'] ?? 0) : 0;
        $data['tax_credits'] = array_values($data['tax_credits'] ?? []);
        $data['status'] = !empty($data['end_date']) && Carbon::parse($data['end_date'])->lt(today()) ? 'terminated' : 'active';

        return $data;
    }

    // ---- pay items -------------------------------------------------------------------

    public function items(): View
    {
        return view('payroll.items', [
            'items' => PayItem::with('account')->orderBy('type')->orderBy('code')->get(),
            'accounts' => Account::where('allow_posting', true)->where('status', true)->whereIn('type', ['expense', 'liability'])->orderBy('code')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        PayItem::create($this->validatedItem($request));

        return back()->with('success', 'Pay item added.');
    }

    public function updateItem(Request $request, PayItem $item): RedirectResponse
    {
        $item->update($this->validatedItem($request, $item));

        return back()->with('success', 'Pay item updated.');
    }

    private function validatedItem(Request $request, ?PayItem $item = null): array
    {
        $type = $request->input('type');
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('payroll_pay_items')->where('company_id', $request->user()->company_id)->ignore($item)],
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'category' => ['required', Rule::in(array_keys($type === 'deduction' ? PayItem::DEDUCTION_CATEGORIES : PayItem::EARNING_CATEGORIES))],
            'calculation' => ['required', Rule::in(array_keys(PayItem::CALCULATIONS))],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'taxable' => ['nullable', 'boolean'], 'pre_tax' => ['nullable', 'boolean'], 'status' => ['nullable', 'boolean'],
            'gl_account_id' => ['nullable', (new TenantExists('accounting_accounts'))->where('allow_posting', true)],
        ]);

        return ['default_amount' => $data['default_amount'] ?? 0, 'gl_account_id' => $data['gl_account_id'] ?? null,
            'taxable' => $type === 'earning' && $request->boolean('taxable'), 'pre_tax' => $type === 'deduction' && $request->boolean('pre_tax'),
            'status' => $request->boolean('status', true)] + $data;
    }

    // ---- statutory settings ---------------------------------------------------------------

    public function settings(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);
        $currencies = Currency::where('status', true)->orderBy('code')->pluck('code');
        $currency = strtoupper((string) $request->query('currency', $currencies->first()));
        $setting = StatutorySetting::with('bands')->where('tax_year', $year)->where('currency', $currency)->first();
        $published = self::published($year, $currency);
        // Pre-fill the form with the published figures when nothing is saved yet, or when asked to.
        $prefill = $published && (!$setting || $request->boolean('published')) ? $published : null;

        return view('payroll.settings', [
            'year' => $year, 'currency' => $currency, 'currencies' => $currencies, 'setting' => $setting,
            'published' => $published, 'prefill' => $prefill,
            'existing' => StatutorySetting::withCount('bands')->orderByDesc('tax_year')->orderBy('currency')->get(),
            'liabilityAccounts' => Account::where('type', 'liability')->where('allow_posting', true)->where('status', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /** Published ZIMRA / NSSA figures for a tax year and currency (config/payroll_statutory.php), or null. */
    public static function published(int $year, string $currency): ?array
    {
        $setKey = config('payroll_statutory.years.'.$year);
        $set = $setKey ? config('payroll_statutory.sets.'.$setKey) : null;
        $values = $set['currencies'][$currency] ?? null;

        return $values ? $values + ['label' => $set['label'], 'source' => $set['source'], 'set_year' => (int) $setKey] : null;
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tax_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'currency' => ['required', 'size:3', TenantExists::currency()],
            'nssa_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'nssa_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'nssa_ceiling' => ['nullable', 'numeric', 'min:0'],
            'nssa_ceiling_currency' => ['nullable', 'size:3'],
            'nssa_deductible' => ['nullable', 'boolean'],
            'aids_levy_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'medical_aid_credit_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.lower' => ['required', 'numeric', 'min:0'],
            'bands.*.upper' => ['nullable', 'numeric', 'gt:bands.*.lower'],
            'bands.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.deduct' => ['nullable', 'numeric', 'min:0'],
            'credits' => ['nullable', 'array'],
            'credits.*.code' => ['required_with:credits.*.name', 'nullable', 'alpha_dash', 'max:40'],
            'credits.*.name' => ['nullable', 'max:120'],
            'credits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'credits.*.currency' => ['nullable', 'size:3'],
            'levies' => ['nullable', 'array'],
            'levies.*.name' => ['nullable', 'max:120'],
            'levies.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'levies.*.payer' => ['nullable', Rule::in(['employer', 'employee'])],
            'levies.*.account_id' => ['nullable', new TenantExists('accounting_accounts')],
        ]);

        $bands = collect($data['bands'])->sortBy(fn ($b) => (float) $b['lower'])->values();
        if ((float) $bands->first()['lower'] > 0) {
            return back()->withErrors(['bands' => 'The first band must start at 0.'])->withInput();
        }

        DB::transaction(function () use ($data, $bands, $request) {
            $setting = StatutorySetting::firstOrNew(['tax_year' => $data['tax_year'], 'currency' => strtoupper($data['currency'])]);
            $setting->fill([
                'nssa_employee_rate' => $data['nssa_employee_rate'], 'nssa_employer_rate' => $data['nssa_employer_rate'],
                'nssa_ceiling' => $data['nssa_ceiling'] ?? null, 'nssa_deductible' => $request->boolean('nssa_deductible'),
                'nssa_ceiling_currency' => !empty($data['nssa_ceiling_currency']) && strtoupper($data['nssa_ceiling_currency']) !== strtoupper($data['currency']) ? strtoupper($data['nssa_ceiling_currency']) : null,
                'aids_levy_rate' => $data['aids_levy_rate'], 'medical_aid_credit_rate' => $data['medical_aid_credit_rate'] ?? 0,
                'credits' => collect($data['credits'] ?? [])->filter(fn ($c) => filled($c['name'] ?? null))->map(fn ($c) => ['code' => strtolower($c['code']), 'name' => $c['name'], 'amount' => (float) ($c['amount'] ?? 0)]
                    + (!empty($c['currency']) && strtoupper($c['currency']) !== strtoupper($data['currency']) ? ['currency' => strtoupper($c['currency'])] : []))->values()->all(),
                'levies' => collect($data['levies'] ?? [])->filter(fn ($l) => filled($l['name'] ?? null) && (float) ($l['rate'] ?? 0) > 0)->map(fn ($l) => ['name' => $l['name'], 'rate' => (float) $l['rate'], 'payer' => $l['payer'] ?? 'employer', 'account_id' => isset($l['account_id']) ? (int) $l['account_id'] : null])->values()->all(),
            ]);
            $setting->save();
            $before = $setting->bands()->get(['lower', 'upper', 'rate', 'deduct'])->toArray();
            $setting->bands()->delete();
            foreach ($bands as $band) {
                $setting->bands()->create(['company_id' => $setting->company_id, 'lower' => $band['lower'], 'upper' => $band['upper'] ?? null, 'rate' => $band['rate'], 'deduct' => $band['deduct'] ?? 0]);
            }
            app(\App\Modules\Accounting\Services\AuditTrailService::class)->record('payroll.tax_table.saved', $setting, ['bands' => $before], ['bands' => $bands->all()], $request);
        });

        return redirect()->route('accounting.payroll.settings.index', ['year' => $data['tax_year'], 'currency' => strtoupper($data['currency'])])
            ->with('success', strtoupper($data['currency']).' '.$data['tax_year'].' statutory settings saved.');
    }

    // ---- runs ----------------------------------------------------------------------------

    public function runs(): View
    {
        $next = PayrollRun::whereIn('status', ['approved', 'paid'])->latest('period_end')->value('period_end');
        $start = $next ? Carbon::parse($next)->addDay()->startOfMonth() : today()->startOfMonth();

        return view('payroll.runs.index', [
            'runs' => PayrollRun::with(['creator', 'approver'])->latest('period_end')->latest('id')->paginate(24),
            'suggested' => ['start' => $start, 'end' => $start->copy()->endOfMonth(), 'pay' => $start->copy()->endOfMonth()],
        ]);
    }

    public function storeRun(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['required', 'date'],
            'notes' => ['nullable', 'max:1000'],
        ]);
        $run = $this->runs->create(Carbon::parse($data['period_start']), Carbon::parse($data['period_end']), Carbon::parse($data['pay_date']), $data['notes'] ?? null);

        return redirect()->route('accounting.payroll.runs.show', $run)->with('success', 'Payroll '.$run->run_number.' created. Enter overtime and other variable pay, then calculate.');
    }

    public function showRun(PayrollRun $run): View
    {
        $employees = $this->runs->employeesFor($run);

        return view('payroll.runs.show', [
            'run' => $run->load(['creator', 'approver']),
            'payslips' => $run->payslips()->with('employee')->get()->groupBy('employee_id'),
            'employees' => $employees,
            'inputs' => $run->inputs()->with(['payItem', 'employee'])->get(),
            'payItems' => PayItem::where('status', true)->orderBy('type')->orderBy('name')->get(),
            'missing' => $run->status === 'draft' ? $this->runs->missingSettings($run, $employees) : [],
        ]);
    }

    public function storeInput(Request $request, PayrollRun $run): RedirectResponse
    {
        abort_unless($run->status === 'draft', 422, 'Only draft payrolls can be changed.');
        $data = $request->validate([
            'employee_id' => ['required', new TenantExists('payroll_employees')],
            'pay_item_id' => ['required', new TenantExists('payroll_pay_items')],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'required_without:quantity'],
        ]);
        $run->inputs()->create($data + ['company_id' => $run->company_id]);
        $run->update(['calculated_at' => null]);

        return back()->with('success', 'Added. Recalculate the payroll to include it.');
    }

    public function destroyInput(PayrollRun $run, RunInput $input): RedirectResponse
    {
        abort_unless($run->status === 'draft' && $input->payroll_run_id === $run->id, 422);
        $input->delete();
        $run->update(['calculated_at' => null]);

        return back()->with('success', 'Removed. Recalculate the payroll.');
    }

    public function calculate(PayrollRun $run): RedirectResponse
    {
        $run = $this->runs->calculate($run);
        $warnings = $run->payslips()->where('net', '<', 0)->count();

        return back()->with($warnings ? 'warning' : 'success', 'Calculated pay for '.$run->employee_count.' '.str('employee')->plural($run->employee_count).'.'
            .($warnings ? ' '.$warnings.' payslip(s) have negative net pay: fix them before approval.' : ' Review the register, then submit for approval.'));
    }

    public function approve(PayrollRun $run): RedirectResponse
    {
        $run = $this->runs->approve($run);

        return back()->with('success', 'Payroll '.$run->run_number.' approved and posted to the ledger.');
    }

    public function markPaid(Request $request, PayrollRun $run): RedirectResponse
    {
        $data = $request->validate(['paid_on' => ['nullable', 'date']]);
        $this->runs->markPaid($run, isset($data['paid_on']) ? Carbon::parse($data['paid_on']) : null);

        return back()->with('success', 'Net pay recorded as paid out of cash, bank and mobile money.');
    }

    public function cancel(PayrollRun $run): RedirectResponse
    {
        $this->runs->cancel($run);

        return back()->with('success', 'Payroll cancelled.');
    }

    public function payslip(PayrollRun $run, Employee $employee): Response
    {
        $slips = $run->payslips()->where('employee_id', $employee->id)->get();
        abort_if($slips->isEmpty(), 404);

        return Pdf::loadView('payroll.payslip-pdf', [
            'run' => $run, 'employee' => $employee, 'slips' => $slips, 'company' => auth()->user()->company,
            'ytd' => $this->runs->yearToDate($employee, $run),
        ])->setPaper('a4', 'portrait')->stream('payslip-'.$run->run_number.'-'.$employee->employee_number.'.pdf');
    }

    public function export(PayrollRun $run, string $report): StreamedResponse
    {
        [$rows, $headings] = match ($report) {
            'register' => [$run->payslips()->with('employee')->get()->map(fn ($p) => [
                'employee_number' => $p->employee->employee_number, 'name' => $p->employee->name, 'department' => $p->employee->department, 'currency' => $p->currency,
                'gross' => $p->gross, 'taxable' => $p->taxable, 'paye' => $p->paye, 'aids_levy' => $p->aids_levy, 'nssa_employee' => $p->nssa_employee,
                'other_deductions' => $p->other_deductions, 'net' => $p->net, 'nssa_employer' => $p->nssa_employer, 'employer_levies' => $p->employer_levies,
            ]), ['Employee no.', 'Name', 'Department', 'Currency', 'Gross', 'Taxable', 'PAYE', 'AIDS levy', 'NSSA (employee)', 'Other deductions', 'Net pay', 'NSSA (employer)', 'Employer levies']],
            'paye' => [$this->runs->payeSchedule($run), ['Employee no.', 'Name', 'TIN', 'National ID', 'Currency', 'Gross', 'Taxable income', 'PAYE', 'AIDS levy', 'Total tax']],
            'nssa' => [$this->runs->nssaSchedule($run), ['Employee no.', 'Name', 'NSSA number', 'National ID', 'Date of birth', 'Currency', 'Insurable earnings', 'Employee', 'Employer', 'Total']],
            'bank' => [$this->runs->paymentList($run), ['Employee no.', 'Name', 'Method', 'Bank', 'Branch', 'Account / wallet', 'Currency', 'Amount', 'Reference']],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows, $headings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row));
            }
            fclose($out);
        }, $run->run_number.'-'.$report.'.csv', ['Content-Type' => 'text/csv']);
    }
}
