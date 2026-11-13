<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Services\CompanySetup\StandardChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll: employees, pay items, statutory settings and tax tables per tax year and currency, payroll runs
 * and payslips. (The older `employees` / `payslip_records` tables are not used: they have no company scope.)
 * Index names are kept under MySQL's 64-character limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('employee_number', 40);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('national_id', 40)->nullable();
            $table->string('tin', 40)->nullable();
            $table->string('nssa_number', 40)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('cost_centre', 60)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active'); // active, terminated
            $table->decimal('basic_salary', 18, 2)->default(0); // monthly, in salary_currency
            $table->string('salary_currency', 3);
            $table->string('secondary_currency', 3)->nullable();
            $table->decimal('secondary_percent', 6, 2)->default(0); // share of pay in the secondary currency
            $table->string('payment_method', 20)->default('bank'); // bank, mobile_money, cash
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('mobile_wallet', 40)->nullable();
            $table->json('tax_credits')->nullable(); // codes of the statutory credits that apply
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'employee_number'], 'pay_employees_company_number_unique');
        });

        Schema::create('payroll_pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 12); // earning, deduction
            $table->string('category', 20); // allowance, overtime, bonus, commission, benefit, pension, medical_aid, loan, union, other
            $table->string('calculation', 20)->default('fixed'); // fixed, percent_of_basic, hourly
            $table->decimal('default_amount', 18, 4)->default(0); // amount, % or hourly rate
            $table->boolean('taxable')->default(true);   // earnings: counts towards taxable income
            $table->boolean('pre_tax')->default(false);  // deductions: reduces taxable income (e.g. approved pension)
            $table->foreignId('gl_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'pay_items_company_code_unique');
        });

        Schema::create('payroll_employee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('payroll_employees')->cascadeOnDelete();
            $table->foreignId('pay_item_id')->constrained('payroll_pay_items')->restrictOnDelete();
            $table->decimal('amount', 18, 4)->nullable(); // overrides the item default (amount, % or rate)
            $table->decimal('quantity', 14, 4)->nullable(); // hours for hourly items
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_statutory_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->string('currency', 3);
            $table->decimal('nssa_employee_rate', 6, 3)->default(0);
            $table->decimal('nssa_employer_rate', 6, 3)->default(0);
            $table->decimal('nssa_ceiling', 18, 2)->nullable(); // monthly insurable earnings ceiling
            $table->boolean('nssa_deductible')->default(true);  // NSSA reduces taxable income
            $table->decimal('aids_levy_rate', 6, 3)->default(0); // % of PAYE
            $table->decimal('medical_aid_credit_rate', 6, 3)->default(0); // % of medical aid contributions
            $table->json('credits')->nullable(); // [{code, name, amount}] fixed monthly tax credits
            $table->json('levies')->nullable();  // [{name, rate, payer: employer|employee, account_id}] % of gross
            $table->timestamps();
            $table->unique(['company_id', 'tax_year', 'currency'], 'pay_stat_company_year_cur_unique');
        });

        Schema::create('payroll_tax_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('statutory_setting_id')->constrained('payroll_statutory_settings')->cascadeOnDelete();
            $table->decimal('lower', 18, 2);              // monthly taxable income from
            $table->decimal('upper', 18, 2)->nullable();  // to (null = and above)
            $table->decimal('rate', 6, 3);                // %
            $table->decimal('deduct', 18, 2)->default(0); // ZIMRA "deduct" amount for the band
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('run_number', 40);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->string('status', 20)->default('draft'); // draft (calculated, awaiting approval), approved (posted), paid, cancelled
            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_cost_base', 18, 2)->default(0); // gross + employer contributions, base currency
            $table->json('totals')->nullable(); // per currency
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('journal_entry_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'run_number'], 'pay_runs_company_number_unique');
        });

        Schema::create('payroll_run_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('payroll_employees')->cascadeOnDelete();
            $table->foreignId('pay_item_id')->constrained('payroll_pay_items')->restrictOnDelete();
            $table->decimal('quantity', 14, 4)->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->timestamps();
        });

        // One payslip line per employee per pay currency.
        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('payroll_employees')->restrictOnDelete();
            $table->string('currency', 3);
            $table->decimal('gross', 18, 2)->default(0);
            $table->decimal('taxable', 18, 2)->default(0);
            $table->decimal('paye', 18, 2)->default(0);
            $table->decimal('aids_levy', 18, 2)->default(0);
            $table->decimal('nssa_employee', 18, 2)->default(0);
            $table->decimal('nssa_employer', 18, 2)->default(0);
            $table->decimal('other_deductions', 18, 2)->default(0);
            $table->decimal('employer_levies', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->index(['payroll_run_id', 'employee_id'], 'pay_payslips_run_employee_idx');
        });

        $chart = app(StandardChartOfAccounts::class);
        foreach (Company::query()->pluck('id') as $companyId) {
            if (Account::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
                $chart->install(Company::find($companyId), ['2220', '2230', '6110']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_run_inputs');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_tax_bands');
        Schema::dropIfExists('payroll_statutory_settings');
        Schema::dropIfExists('payroll_employee_items');
        Schema::dropIfExists('payroll_pay_items');
        Schema::dropIfExists('payroll_employees');
    }
};
