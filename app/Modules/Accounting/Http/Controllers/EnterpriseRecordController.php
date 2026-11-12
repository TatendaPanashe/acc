<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Rules\TenantExists;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\ApprovalRequest;
use App\Modules\Accounting\Models\AiForecast;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\CarbonActivity;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\Opportunity;
use App\Modules\Accounting\Models\PortalAccess;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Models\RevenueContract;
use App\Modules\Accounting\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnterpriseRecordController extends Controller
{
    public function store(Request $request, string $module): RedirectResponse
    {
        $config = $this->config($module);
        $config['model']::create($this->validated($request, $module));

        return back()->with('success', $config['singular'].' created.');
    }

    public function edit(string $module, int $record): View
    {
        $config = $this->config($module);

        return view('accounting.modules.records.edit', [
            'module' => $config['module'],
            'moduleKey' => $module,
            'record' => $this->findRecord($module, $record),
            'fields' => $this->fields($module),
            'returnUrl' => $this->moduleUrl($module),
        ]);
    }

    public function update(Request $request, string $module, int $record): RedirectResponse
    {
        $config = $this->config($module);
        $model = $this->findRecord($module, $record);
        $model->update($this->validated($request, $module, $model));

        return redirect($this->moduleUrl($module))->with('success', $config['singular'].' updated.');
    }

    public function destroy(string $module, int $record): RedirectResponse
    {
        $config = $this->config($module);
        $model = $this->findRecord($module, $record);

        if (array_key_exists('status', $model->getAttributes())) {
            if (is_bool($model->status)) {
                $model->update(['status' => false]);
            } elseif ($config['delete_status'] ?? null) {
                $model->update(['status' => $config['delete_status']]);
            } else {
                $model->delete();
            }
        } else {
            $model->delete();
        }

        return back()->with('success', $config['singular'].' removed.');
    }

    private function findRecord(string $module, int $id): Model
    {
        $model = $this->config($module)['model'];

        return $model::findOrFail($id);
    }

    private function validated(Request $request, string $module, ?Model $record = null): array
    {
        $rules = match ($module) {
            'banking' => [
                'bank_account_name' => ['required', 'max:255'],
                'format' => ['required', Rule::in(['MT940', 'CAMT.053', 'OFX', 'CSV'])],
                'statement_date' => ['required', 'date'],
                'file_path' => ['nullable', 'max:255'],
                'status' => ['required', Rule::in(['imported', 'matched', 'reconciled'])],
            ],
            'fixed-assets' => [
                'asset_number' => ['required', 'max:60', Rule::unique('accounting_fixed_assets')->where('company_id', $request->user()->company_id)->ignore($record)],
                'name' => ['required', 'max:255'],
                'category' => ['required', 'max:255'],
                'acquired_on' => ['required', 'date'],
                'cost' => ['required', 'numeric', 'min:0'],
                'salvage_value' => ['required', 'numeric', 'min:0'],
                'useful_life_months' => ['required', 'integer', 'min:1'],
                'depreciation_method' => ['required', Rule::in(['straight_line', 'declining_balance'])],
                'status' => ['required', Rule::in(['active', 'disposed', 'transferred', 'retired'])],
            ],
            'reporting-analytics' => [
                'code' => ['required', 'max:80', Rule::unique('accounting_report_definitions')->where('company_id', $request->user()->company_id)->ignore($record)],
                'name' => ['required', 'max:255'],
                'type' => ['required', Rule::in(['financial', 'operational', 'tax', 'management'])],
                'format' => ['required', Rule::in(['pdf', 'excel', 'dashboard'])],
                'status' => ['nullable', 'boolean'],
            ],
            'crm' => [
                'customer_id' => ['nullable', new TenantExists('accounting_customers')],
                'name' => ['required', 'max:255'],
                'stage' => ['required', Rule::in(['prospecting', 'proposal', 'won', 'lost'])],
                'value' => ['required', 'numeric', 'min:0'],
                'expected_close_on' => ['nullable', 'date'],
            ],
            'bom-job-costing' => [
                'job_number' => ['required', 'max:80', Rule::unique('accounting_job_costs')->where('company_id', $request->user()->company_id)->ignore($record)],
                'name' => ['required', 'max:255'],
                'type' => ['required', Rule::in(['bom', 'work_order', 'project'])],
                'estimated_cost' => ['required', 'numeric', 'min:0'],
                'actual_cost' => ['required', 'numeric', 'min:0'],
                'due_on' => ['nullable', 'date'],
                'status' => ['required', Rule::in(['planned', 'wip', 'variance_review', 'closed'])],
                'notes' => ['nullable', 'max:4000'],
            ],
            'annuity-billing' => [
                'customer_id' => ['nullable', new TenantExists('accounting_customers')],
                'contract_number' => ['required', 'max:80', Rule::unique('accounting_subscriptions')->where('company_id', $request->user()->company_id)->ignore($record)],
                'description' => ['required', 'max:255'],
                'recurring_amount' => ['required', 'numeric', 'min:0'],
                'currency' => ['required', 'size:3', TenantExists::currency()],
                'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
                'next_invoice_on' => ['nullable', 'date'],
                'status' => ['required', Rule::in(['draft', 'active', 'renewal_due', 'cancelled'])],
            ],
            'approvals-audit' => [
                'workflow_key' => ['required', 'max:80'],
                'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
                'resolution_note' => ['nullable', 'max:4000'],
            ],
            'ai-automation' => [
                'model_key' => ['required', 'max:80', Rule::unique('accounting_ai_forecasts')->where('company_id', $request->user()->company_id)->ignore($record)],
                'type' => ['required', Rule::in(['cash_flow', 'revenue', 'inventory', 'expense', 'auto_coding'])],
                'source_module' => ['required', 'max:80'],
                'forecast_date' => ['nullable', 'date'],
                'predicted_value' => ['required', 'numeric'],
                'confidence' => ['required', 'numeric', 'min:0', 'max:100'],
                'status' => ['required', Rule::in(['draft', 'review', 'approved', 'rejected'])],
            ],
            'client-portal' => [
                'customer_id' => ['nullable', new TenantExists('accounting_customers')],
                'portal_token' => ['required', 'max:80', Rule::unique('accounting_portal_accesses')->ignore($record)],
                'contact_name' => ['required', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'expires_at' => ['nullable', 'date'],
                'status' => ['nullable', 'boolean'],
            ],
            'revenue-recognition' => [
                'customer_id' => ['nullable', new TenantExists('accounting_customers')],
                'contract_number' => ['required', 'max:80', Rule::unique('accounting_revenue_contracts')->where('company_id', $request->user()->company_id)->ignore($record)],
                'customer_name' => ['nullable', 'max:255'],
                'transaction_price' => ['required', 'numeric', 'min:0'],
                'performance_obligations' => ['required', 'integer', 'min:1'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
                'recognition_method' => ['required', Rule::in(['point_in_time', 'over_time', 'milestone'])],
                'status' => ['required', Rule::in(['draft', 'active', 'complete', 'cancelled'])],
            ],
            'einvoicing-tax-mesh' => [
                'document_number' => ['required', 'max:80', Rule::unique('accounting_einvoice_documents')->where('company_id', $request->user()->company_id)->ignore($record)],
                'jurisdiction' => ['required', 'size:3'],
                'network' => ['required', 'max:255'],
                'taxable_amount' => ['required', 'numeric', 'min:0'],
                'tax_amount' => ['required', 'numeric', 'min:0'],
                'status' => ['required', Rule::in(['draft', 'submitted', 'cleared', 'rejected', 'cancelled'])],
                'authority_reference' => ['nullable', 'max:255'],
            ],
            'esg-carbon' => [
                'activity_code' => ['required', 'max:80', Rule::unique('accounting_carbon_activities')->where('company_id', $request->user()->company_id)->ignore($record)],
                'scope' => ['required', Rule::in(['scope_1', 'scope_2', 'scope_3'])],
                'category' => ['required', 'max:255'],
                'quantity' => ['required', 'numeric', 'min:0'],
                'unit' => ['required', 'max:30'],
                'emission_factor' => ['required', 'numeric', 'min:0'],
                'activity_date' => ['required', 'date'],
                'evidence_reference' => ['nullable', 'max:255'],
                'status' => ['required', Rule::in(['draft', 'verified', 'reported'])],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if ($module === 'reporting-analytics') {
            $data['status'] = $request->boolean('status');
            $data['filters'] = ['period' => 'current', 'drill_down' => true];
        }

        if ($module === 'approvals-audit' && !$record) {
            $data['requested_by'] = $request->user()->id;
        }

        if ($module === 'client-portal') {
            $data['status'] = $request->boolean('status', true);
            $data['permissions'] = ['invoices' => true, 'statements' => true, 'tickets' => true];
        }

        if ($module === 'einvoicing-tax-mesh') {
            $data['jurisdiction'] = strtoupper($data['jurisdiction']);
            $data['payload'] = ['source' => 'manual', 'schema_version' => '2026-08'];
        }

        if ($module === 'esg-carbon') {
            $data['co2e'] = round(((float) $data['quantity']) * ((float) $data['emission_factor']), 4);
        }

        if ($module === 'ai-automation') {
            $data['features'] = ['created_by' => $request->user()->id, 'review_required' => $data['confidence'] < 80];
        }

        return $data;
    }

    private function config(string $module): array
    {
        $modules = config('accounting.modules');

        return match ($module) {
            'banking' => ['module' => $modules['banking'], 'model' => BankStatement::class, 'singular' => 'Bank statement', 'delete_status' => 'reconciled'],
            'fixed-assets' => ['module' => $modules['fixed-assets'], 'model' => FixedAsset::class, 'singular' => 'Fixed asset', 'delete_status' => 'retired'],
            'reporting-analytics' => ['module' => $modules['reporting-analytics'], 'model' => ReportDefinition::class, 'singular' => 'Report definition'],
            'crm' => ['module' => $modules['crm'], 'model' => Opportunity::class, 'singular' => 'Opportunity'],
            'bom-job-costing' => ['module' => $modules['bom-job-costing'], 'model' => JobCost::class, 'singular' => 'Job'],
            'annuity-billing' => ['module' => $modules['annuity-billing'], 'model' => Subscription::class, 'singular' => 'Subscription'],
            'approvals-audit' => ['module' => $modules['approvals-audit'], 'model' => ApprovalRequest::class, 'singular' => 'Approval request', 'delete_status' => 'cancelled'],
            'ai-automation' => ['module' => $modules['ai-automation'], 'model' => AiForecast::class, 'singular' => 'AI forecast', 'delete_status' => 'rejected'],
            'client-portal' => ['module' => $modules['client-portal'], 'model' => PortalAccess::class, 'singular' => 'Portal access'],
            'revenue-recognition' => ['module' => $modules['revenue-recognition'], 'model' => RevenueContract::class, 'singular' => 'Revenue contract', 'delete_status' => 'cancelled'],
            'einvoicing-tax-mesh' => ['module' => $modules['einvoicing-tax-mesh'], 'model' => EinvoiceDocument::class, 'singular' => 'E-invoice document', 'delete_status' => 'cancelled'],
            'esg-carbon' => ['module' => $modules['esg-carbon'], 'model' => CarbonActivity::class, 'singular' => 'Carbon activity', 'delete_status' => 'reported'],
            default => abort(404),
        };
    }

    private function moduleUrl(string $module): string
    {
        return match ($module) {
            'banking' => route('accounting.banking.index'),
            'fixed-assets' => route('accounting.fixed-assets.index'),
            'reporting-analytics' => route('accounting.reporting.index'),
            'crm' => route('accounting.crm.index'),
            'bom-job-costing' => route('accounting.bom-job-costing.index'),
            'annuity-billing' => route('accounting.annuity-billing.index'),
            'approvals-audit' => route('accounting.approvals-audit.index'),
            'ai-automation' => route('accounting.ai-automation.index'),
            'client-portal' => route('accounting.client-portal.index'),
            'revenue-recognition' => route('accounting.revenue-recognition.index'),
            'einvoicing-tax-mesh' => route('accounting.einvoicing-tax-mesh.index'),
            'esg-carbon' => route('accounting.esg-carbon.index'),
            default => route('dashboard'),
        };
    }

    public function fields(string $module): array
    {
        $customers = Customer::orderBy('name')->pluck('name', 'id')->prepend('No linked customer', '')->all();

        return match ($module) {
            'banking' => [
                ['name' => 'bank_account_name', 'label' => 'Bank Account', 'type' => 'text'],
                ['name' => 'format', 'label' => 'Import Format', 'type' => 'select', 'options' => ['MT940' => 'MT940', 'CAMT.053' => 'CAMT.053', 'OFX' => 'OFX', 'CSV' => 'CSV']],
                ['name' => 'statement_date', 'label' => 'Statement Date', 'type' => 'date'],
                ['name' => 'file_path', 'label' => 'File Reference', 'type' => 'text'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['imported' => 'Imported', 'matched' => 'Matched', 'reconciled' => 'Reconciled']],
            ],
            'fixed-assets' => [
                ['name' => 'asset_number', 'label' => 'Asset Number', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Asset Name', 'type' => 'text'],
                ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['name' => 'acquired_on', 'label' => 'Acquired On', 'type' => 'date'],
                ['name' => 'cost', 'label' => 'Cost', 'type' => 'number'],
                ['name' => 'salvage_value', 'label' => 'Salvage Value', 'type' => 'number'],
                ['name' => 'useful_life_months', 'label' => 'Useful Life Months', 'type' => 'number'],
                ['name' => 'depreciation_method', 'label' => 'Depreciation', 'type' => 'select', 'options' => ['straight_line' => 'Straight Line', 'declining_balance' => 'Declining Balance']],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'disposed' => 'Disposed', 'transferred' => 'Transferred', 'retired' => 'Retired']],
            ],
            'reporting-analytics' => [
                ['name' => 'code', 'label' => 'Report Code', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Report Name', 'type' => 'text'],
                ['name' => 'type', 'label' => 'Report Type', 'type' => 'select', 'options' => ['financial' => 'Financial', 'operational' => 'Operational', 'tax' => 'Tax', 'management' => 'Management']],
                ['name' => 'format', 'label' => 'Output', 'type' => 'select', 'options' => ['pdf' => 'PDF', 'excel' => 'Excel', 'dashboard' => 'Dashboard']],
                ['name' => 'status', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'crm' => [
                ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $customers],
                ['name' => 'name', 'label' => 'Opportunity', 'type' => 'text'],
                ['name' => 'stage', 'label' => 'Stage', 'type' => 'select', 'options' => ['prospecting' => 'Prospecting', 'proposal' => 'Proposal', 'won' => 'Won', 'lost' => 'Lost']],
                ['name' => 'value', 'label' => 'Value', 'type' => 'number'],
                ['name' => 'expected_close_on', 'label' => 'Expected Close', 'type' => 'date'],
            ],
            'bom-job-costing' => [
                ['name' => 'job_number', 'label' => 'Job Number', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Job Name', 'type' => 'text'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['bom' => 'BOM', 'work_order' => 'Work Order', 'project' => 'Project']],
                ['name' => 'estimated_cost', 'label' => 'Estimated Cost', 'type' => 'number'],
                ['name' => 'actual_cost', 'label' => 'Actual Cost', 'type' => 'number'],
                ['name' => 'due_on', 'label' => 'Due On', 'type' => 'date'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['planned' => 'Planned', 'wip' => 'WIP', 'variance_review' => 'Variance Review', 'closed' => 'Closed']],
                ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
            ],
            'annuity-billing' => [
                ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $customers],
                ['name' => 'contract_number', 'label' => 'Contract Number', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'text'],
                ['name' => 'recurring_amount', 'label' => 'Recurring Amount', 'type' => 'number'],
                ['name' => 'currency', 'label' => 'Currency', 'type' => 'text'],
                ['name' => 'billing_cycle', 'label' => 'Billing Cycle', 'type' => 'select', 'options' => ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual']],
                ['name' => 'next_invoice_on', 'label' => 'Next Invoice On', 'type' => 'date'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'active' => 'Active', 'renewal_due' => 'Renewal Due', 'cancelled' => 'Cancelled']],
            ],
            'approvals-audit' => [
                ['name' => 'workflow_key', 'label' => 'Workflow', 'type' => 'text'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']],
                ['name' => 'resolution_note', 'label' => 'Resolution Note', 'type' => 'textarea'],
            ],
            'ai-automation' => [
                ['name' => 'model_key', 'label' => 'Model Key', 'type' => 'text'],
                ['name' => 'type', 'label' => 'Use Case', 'type' => 'select', 'options' => ['cash_flow' => 'Cash Flow', 'revenue' => 'Revenue', 'inventory' => 'Inventory', 'expense' => 'Expense', 'auto_coding' => 'Auto Coding']],
                ['name' => 'source_module', 'label' => 'Source Module', 'type' => 'text'],
                ['name' => 'forecast_date', 'label' => 'Forecast Date', 'type' => 'date'],
                ['name' => 'predicted_value', 'label' => 'Predicted Value', 'type' => 'number'],
                ['name' => 'confidence', 'label' => 'Confidence %', 'type' => 'number'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'review' => 'Review', 'approved' => 'Approved', 'rejected' => 'Rejected']],
            ],
            'client-portal' => [
                ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $customers],
                ['name' => 'portal_token', 'label' => 'Portal Token', 'type' => 'text'],
                ['name' => 'contact_name', 'label' => 'Contact Name', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['name' => 'expires_at', 'label' => 'Expires At', 'type' => 'date'],
                ['name' => 'status', 'label' => 'Active', 'type' => 'checkbox'],
            ],
            'revenue-recognition' => [
                ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'options' => $customers],
                ['name' => 'contract_number', 'label' => 'Contract Number', 'type' => 'text'],
                ['name' => 'customer_name', 'label' => 'Customer Name Override', 'type' => 'text'],
                ['name' => 'transaction_price', 'label' => 'Transaction Price', 'type' => 'number'],
                ['name' => 'performance_obligations', 'label' => 'Performance Obligations', 'type' => 'number'],
                ['name' => 'starts_on', 'label' => 'Starts On', 'type' => 'date'],
                ['name' => 'ends_on', 'label' => 'Ends On', 'type' => 'date'],
                ['name' => 'recognition_method', 'label' => 'Recognition Method', 'type' => 'select', 'options' => ['point_in_time' => 'Point in Time', 'over_time' => 'Over Time', 'milestone' => 'Milestone']],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'active' => 'Active', 'complete' => 'Complete', 'cancelled' => 'Cancelled']],
            ],
            'einvoicing-tax-mesh' => [
                ['name' => 'document_number', 'label' => 'Document Number', 'type' => 'text'],
                ['name' => 'jurisdiction', 'label' => 'Jurisdiction', 'type' => 'text'],
                ['name' => 'network', 'label' => 'Network', 'type' => 'text'],
                ['name' => 'taxable_amount', 'label' => 'Taxable Amount', 'type' => 'number'],
                ['name' => 'tax_amount', 'label' => 'Tax Amount', 'type' => 'number'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'submitted' => 'Submitted', 'cleared' => 'Cleared', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled']],
                ['name' => 'authority_reference', 'label' => 'Authority Reference', 'type' => 'text'],
            ],
            'esg-carbon' => [
                ['name' => 'activity_code', 'label' => 'Activity Code', 'type' => 'text'],
                ['name' => 'scope', 'label' => 'Scope', 'type' => 'select', 'options' => ['scope_1' => 'Scope 1', 'scope_2' => 'Scope 2', 'scope_3' => 'Scope 3']],
                ['name' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number'],
                ['name' => 'unit', 'label' => 'Unit', 'type' => 'text'],
                ['name' => 'emission_factor', 'label' => 'Emission Factor', 'type' => 'number'],
                ['name' => 'activity_date', 'label' => 'Activity Date', 'type' => 'date'],
                ['name' => 'evidence_reference', 'label' => 'Evidence Reference', 'type' => 'text'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'verified' => 'Verified', 'reported' => 'Reported']],
            ],
            default => [],
        };
    }
}
