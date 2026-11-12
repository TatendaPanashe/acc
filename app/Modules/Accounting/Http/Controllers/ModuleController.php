<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\ApprovalRequest;
use App\Modules\Accounting\Models\BankStatement;
use App\Modules\Accounting\Models\AiForecast;
use App\Modules\Accounting\Models\CarbonActivity;
use App\Modules\Accounting\Models\EinvoiceDocument;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\JobCost;
use App\Modules\Accounting\Models\Opportunity;
use App\Modules\Accounting\Models\PortalAccess;
use App\Modules\Accounting\Models\ReportDefinition;
use App\Modules\Accounting\Models\RevenueContract;
use App\Modules\Accounting\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function show(string $module): View|RedirectResponse
    {
        if ($module === 'point-of-sale') {
            return redirect()->route('accounting.pos.create');
        }

        abort_unless(array_key_exists($module, $this->modules()), 404);

        return view('accounting.modules.show', [
            'module' => $this->modules()[$module],
            'moduleKey' => $module,
            'workspace' => $this->workspace($module),
            'records' => $this->records($module),
            'fields' => app(EnterpriseRecordController::class)->fields($module),
        ]);
    }

    public function banking(): View|RedirectResponse
    {
        return $this->show('banking');
    }

    public function fixedAssets(): View|RedirectResponse
    {
        return $this->show('fixed-assets');
    }

    public function reporting(): View|RedirectResponse
    {
        return $this->show('reporting-analytics');
    }

    public function crm(): View|RedirectResponse
    {
        return $this->show('crm');
    }

    public function bomJobCosting(): View|RedirectResponse
    {
        return $this->show('bom-job-costing');
    }

    public function annuityBilling(): View|RedirectResponse
    {
        return $this->show('annuity-billing');
    }

    public function approvalsAudit(): View|RedirectResponse
    {
        return $this->show('approvals-audit');
    }

    public function aiAutomation(): View|RedirectResponse
    {
        return $this->show('ai-automation');
    }

    public function clientPortal(): View|RedirectResponse
    {
        return $this->show('client-portal');
    }

    public function revenueRecognition(): View|RedirectResponse
    {
        return $this->show('revenue-recognition');
    }

    public function einvoicingTaxMesh(): View|RedirectResponse
    {
        return $this->show('einvoicing-tax-mesh');
    }

    public function esgCarbon(): View|RedirectResponse
    {
        return $this->show('esg-carbon');
    }

    public function settings(): View
    {
        $module = [
            'title' => 'Accounting Settings',
            'icon' => 'settings',
            'description' => 'Currencies, exchange rates, tax codes, fiscal years, accounting periods, workflow rules, and period locks.',
            'capabilities' => ['Currencies', 'Exchange rates', 'Tax codes', 'Fiscal years', 'Accounting periods', 'Approval workflows', 'Period locks'],
        ];

        return view('accounting.modules.show', [
            'module' => $module,
            'moduleKey' => 'settings',
            'workspace' => $this->workspace('settings'),
            'records' => collect(),
            'fields' => [],
        ]);
    }

    private function modules(): array
    {
        return config('accounting.modules');
    }

    private function records(string $module): mixed
    {
        $model = match ($module) {
            'banking' => BankStatement::class,
            'fixed-assets' => FixedAsset::class,
            'reporting-analytics' => ReportDefinition::class,
            'crm' => Opportunity::class,
            'bom-job-costing' => JobCost::class,
            'annuity-billing' => Subscription::class,
            'approvals-audit' => ApprovalRequest::class,
            'ai-automation' => AiForecast::class,
            'client-portal' => PortalAccess::class,
            'revenue-recognition' => RevenueContract::class,
            'einvoicing-tax-mesh' => EinvoiceDocument::class,
            'esg-carbon' => CarbonActivity::class,
            default => null,
        };

        abort_unless($model, 404);

        return $model::latest()->paginate(15);
    }

    private function workspace(string $module): array
    {
        return match ($module) {
            'banking' => [
                'metrics' => [
                    ['label' => 'Imported Statements', 'value' => BankStatement::where('status', 'imported')->count(), 'icon' => 'upload-cloud', 'colour' => 'col-blue'],
                    ['label' => 'Matched Statements', 'value' => BankStatement::where('status', 'matched')->count(), 'icon' => 'shuffle', 'colour' => 'col-green'],
                    ['label' => 'Reconciled', 'value' => BankStatement::where('status', 'reconciled')->count(), 'icon' => 'check-circle', 'colour' => 'col-orange'],
                    ['label' => 'Total Statements', 'value' => BankStatement::count(), 'icon' => 'book', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Statement Imports', 'status' => 'Ready', 'summary' => 'MT940, CAMT.053, OFX, and CSV layouts with duplicate detection and account mapping.'],
                    ['title' => 'Matching Rules', 'status' => 'Active', 'summary' => 'Match deposits, supplier payments, fees, and transfers against GL, AR, and AP records.'],
                    ['title' => 'Reconciliation Desk', 'status' => 'In Review', 'summary' => 'Resolve unmatched lines, split charges, and post bank journals from one queue.'],
                ],
                'recordsTitle' => 'Reconciliation Queue',
                'records' => [
                    ['item' => 'Main ZWL Current Account', 'owner' => 'Treasury', 'amount' => 'ZWL 42,180.00', 'status' => '7 exceptions'],
                    ['item' => 'USD Nostro Account', 'owner' => 'Finance', 'amount' => 'USD 8,940.50', 'status' => 'Ready to close'],
                    ['item' => 'POS Settlement Batch', 'owner' => 'Retail', 'amount' => 'ZWL 16,225.00', 'status' => 'Needs matching'],
                ],
            ],
            'fixed-assets' => [
                'metrics' => [
                    ['label' => 'Assets Registered', 'value' => FixedAsset::count(), 'icon' => 'briefcase', 'colour' => 'col-blue'],
                    ['label' => 'Asset Cost', 'value' => 'ZWL '.number_format((float) FixedAsset::sum('cost'), 2), 'icon' => 'trending-down', 'colour' => 'col-red'],
                    ['label' => 'Transferred', 'value' => FixedAsset::where('status', 'transferred')->count(), 'icon' => 'repeat', 'colour' => 'col-orange'],
                    ['label' => 'Retired', 'value' => FixedAsset::where('status', 'retired')->count(), 'icon' => 'tool', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Asset Register', 'status' => 'Ready', 'summary' => 'Capture asset classes, acquisition details, custodians, locations, and capitalization rules.'],
                    ['title' => 'Depreciation Runs', 'status' => 'Scheduled', 'summary' => 'Straight-line and reducing-balance depreciation with GL posting batches.'],
                    ['title' => 'Disposals & Transfers', 'status' => 'Controlled', 'summary' => 'Route movements, write-offs, and proceeds through approvals and audit history.'],
                ],
                'recordsTitle' => 'Asset Control List',
                'records' => [
                    ['item' => 'Delivery Vehicle Fleet', 'owner' => 'Operations', 'amount' => 'ZWL 320,000.00', 'status' => 'Depreciating'],
                    ['item' => 'Warehouse Racking', 'owner' => 'Stores', 'amount' => 'ZWL 76,400.00', 'status' => 'Maintenance due'],
                    ['item' => 'POS Terminals', 'owner' => 'Retail', 'amount' => 'ZWL 28,900.00', 'status' => 'Transfer review'],
                ],
            ],
            'reporting-analytics' => [
                'metrics' => [
                    ['label' => 'Saved Reports', 'value' => ReportDefinition::count(), 'icon' => 'bar-chart-2', 'colour' => 'col-blue'],
                    ['label' => 'PDF Packs', 'value' => ReportDefinition::where('format', 'pdf')->count(), 'icon' => 'file-text', 'colour' => 'col-red'],
                    ['label' => 'Dashboards', 'value' => ReportDefinition::where('format', 'dashboard')->count(), 'icon' => 'monitor', 'colour' => 'col-green'],
                    ['label' => 'Active Reports', 'value' => ReportDefinition::where('status', true)->count(), 'icon' => 'download', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Report Builder', 'status' => 'Ready', 'summary' => 'Compose financial, operational, and tax packs from reusable filters and layouts.'],
                    ['title' => 'Board Packs', 'status' => 'Drafting', 'summary' => 'Bundle P&L, balance sheet, cash flow, aging, inventory, and commentary into PDFs.'],
                    ['title' => 'Drill-Down Analytics', 'status' => 'Active', 'summary' => 'Trace balances from statement lines to accounts, journals, invoices, and stock movements.'],
                ],
                'recordsTitle' => 'Featured Report Packs',
                'records' => [
                    ['item' => 'Monthly Management Accounts', 'owner' => 'Finance', 'amount' => '12 schedules', 'status' => 'Ready'],
                    ['item' => 'Inventory Margin Review', 'owner' => 'Operations', 'amount' => '8 widgets', 'status' => 'Draft'],
                    ['item' => 'Tax Compliance Pack', 'owner' => 'Tax', 'amount' => '6 returns', 'status' => 'Review'],
                ],
            ],
            'crm' => [
                'metrics' => [
                    ['label' => 'Accounts', 'value' => \App\Modules\Accounting\Models\Customer::count(), 'icon' => 'users', 'colour' => 'col-blue'],
                    ['label' => 'Open Opportunities', 'value' => Opportunity::whereIn('stage', ['prospecting', 'proposal'])->count(), 'icon' => 'target', 'colour' => 'col-green'],
                    ['label' => 'Won Deals', 'value' => Opportunity::where('stage', 'won')->count(), 'icon' => 'life-buoy', 'colour' => 'col-orange'],
                    ['label' => 'Pipeline Value', 'value' => 'ZWL '.number_format((float) Opportunity::sum('value'), 2), 'icon' => 'file-plus', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Customer 360', 'status' => 'Ready', 'summary' => 'Unify AR balances, contacts, activity notes, tickets, and sales history.'],
                    ['title' => 'Pipeline', 'status' => 'Active', 'summary' => 'Track opportunities from lead through quote, sales order, invoice, and payment.'],
                    ['title' => 'Service Desk', 'status' => 'In Review', 'summary' => 'Prioritize customer requests with SLA status and financial account context.'],
                ],
                'recordsTitle' => 'Commercial Focus',
                'records' => [
                    ['item' => 'Top 20 Receivable Accounts', 'owner' => 'Credit Control', 'amount' => 'ZWL 184,000.00', 'status' => 'Follow up'],
                    ['item' => 'Wholesale Pipeline', 'owner' => 'Sales', 'amount' => 'ZWL 620,000.00', 'status' => 'Negotiation'],
                    ['item' => 'Contract Renewals', 'owner' => 'Account Managers', 'amount' => '18 customers', 'status' => 'This quarter'],
                ],
            ],
            'bom-job-costing' => [
                'metrics' => [
                    ['label' => 'Open Jobs', 'value' => JobCost::whereIn('status', ['planned', 'wip', 'variance_review'])->count(), 'icon' => 'layers', 'colour' => 'col-blue'],
                    ['label' => 'WIP Value', 'value' => 'ZWL '.number_format((float) JobCost::where('status', 'wip')->sum('actual_cost'), 2), 'icon' => 'activity', 'colour' => 'col-green'],
                    ['label' => 'Variance Alerts', 'value' => JobCost::where('status', 'variance_review')->count(), 'icon' => 'alert-circle', 'colour' => 'col-orange'],
                    ['label' => 'BOM Jobs', 'value' => JobCost::where('type', 'bom')->count(), 'icon' => 'git-branch', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Bill of Materials', 'status' => 'Ready', 'summary' => 'Build multi-level material structures with alternates, scrap factors, and revisions.'],
                    ['title' => 'Work Orders', 'status' => 'Active', 'summary' => 'Reserve stock, issue materials, capture labour, and receive finished goods.'],
                    ['title' => 'Job Ledger', 'status' => 'Controlled', 'summary' => 'Compare estimated, committed, actual, and invoiced costs by job.'],
                ],
                'recordsTitle' => 'Production & Costing Queue',
                'records' => [
                    ['item' => 'Packaging Line Upgrade', 'owner' => 'Projects', 'amount' => 'ZWL 58,700.00', 'status' => 'WIP'],
                    ['item' => 'Retail Kit Assembly', 'owner' => 'Manufacturing', 'amount' => '420 units', 'status' => 'Materials reserved'],
                    ['item' => 'Custom Installation Job', 'owner' => 'Services', 'amount' => 'ZWL 31,500.00', 'status' => 'Variance review'],
                ],
            ],
            'annuity-billing' => [
                'metrics' => [
                    ['label' => 'Active Contracts', 'value' => Subscription::where('status', 'active')->count(), 'icon' => 'repeat', 'colour' => 'col-blue'],
                    ['label' => 'Recurring Value', 'value' => 'ZWL '.number_format((float) Subscription::where('status', 'active')->sum('recurring_amount'), 2), 'icon' => 'refresh-cw', 'colour' => 'col-green'],
                    ['label' => 'Renewals Due', 'value' => Subscription::where('status', 'renewal_due')->count(), 'icon' => 'calendar', 'colour' => 'col-orange'],
                    ['label' => 'Cancelled', 'value' => Subscription::where('status', 'cancelled')->count(), 'icon' => 'alert-triangle', 'colour' => 'col-red'],
                ],
                'workstreams' => [
                    ['title' => 'Subscription Contracts', 'status' => 'Ready', 'summary' => 'Manage recurring products, billing cadence, proration, contract dates, and price escalations.'],
                    ['title' => 'Invoice Scheduler', 'status' => 'Scheduled', 'summary' => 'Generate draft invoices, review exceptions, and post approved batches into AR.'],
                    ['title' => 'Renewal Control', 'status' => 'Active', 'summary' => 'Track renewal reminders, contract changes, and revenue continuity risks.'],
                ],
                'recordsTitle' => 'Billing Run Preview',
                'records' => [
                    ['item' => 'Monthly Support Contracts', 'owner' => 'Customer Success', 'amount' => 'ZWL 42,900.00', 'status' => 'Ready'],
                    ['item' => 'Quarterly Licence Renewals', 'owner' => 'Sales Ops', 'amount' => 'ZWL 118,000.00', 'status' => 'Approval needed'],
                    ['item' => 'Usage-Based Add-ons', 'owner' => 'Billing', 'amount' => 'ZWL 9,640.00', 'status' => 'Meter import'],
                ],
            ],
            'approvals-audit' => [
                'metrics' => [
                    ['label' => 'Pending Approvals', 'value' => ApprovalRequest::where('status', 'pending')->count(), 'icon' => 'shield', 'colour' => 'col-orange'],
                    ['label' => 'Approved', 'value' => ApprovalRequest::where('status', 'approved')->count(), 'icon' => 'sliders', 'colour' => 'col-blue'],
                    ['label' => 'Audit Events', 'value' => \App\Modules\Accounting\Models\AuditLog::count(), 'icon' => 'database', 'colour' => 'col-green'],
                    ['label' => 'Rejected', 'value' => ApprovalRequest::where('status', 'rejected')->count(), 'icon' => 'lock', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Workflow Rules', 'status' => 'Ready', 'summary' => 'Define thresholds, approver chains, delegation, escalation, and module-specific controls.'],
                    ['title' => 'Approval Inbox', 'status' => 'Active', 'summary' => 'Review journals, invoices, POs, payments, requisitions, and master data changes.'],
                    ['title' => 'Audit Trail', 'status' => 'Immutable', 'summary' => 'Capture creates, updates, postings, reversals, locks, and approval decisions.'],
                ],
                'recordsTitle' => 'Control Queue',
                'records' => [
                    ['item' => 'Supplier Payment Batch AP-0082', 'owner' => 'Finance Manager', 'amount' => 'ZWL 63,800.00', 'status' => 'Pending'],
                    ['item' => 'Stock Adjustment STK-141', 'owner' => 'Operations Lead', 'amount' => 'ZWL 12,450.00', 'status' => 'Escalated'],
                    ['item' => 'July Period Lock', 'owner' => 'Controller', 'amount' => 'GL/AR/AP', 'status' => 'Final review'],
                ],
            ],
            'settings' => [
                'metrics' => [
                    ['label' => 'Currencies', 'value' => '4', 'icon' => 'dollar-sign', 'colour' => 'col-blue'],
                    ['label' => 'Tax Codes', 'value' => '9', 'icon' => 'percent', 'colour' => 'col-green'],
                    ['label' => 'Open Periods', 'value' => '2', 'icon' => 'calendar', 'colour' => 'col-orange'],
                    ['label' => 'Workflow Rules', 'value' => '17', 'icon' => 'settings', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Financial Structure', 'status' => 'Ready', 'summary' => 'Maintain fiscal years, accounting periods, branches, departments, and account segments.'],
                    ['title' => 'Tax & Currency', 'status' => 'Active', 'summary' => 'Control exchange rates, tax codes, withholding treatment, and reporting currencies.'],
                    ['title' => 'Controls', 'status' => 'Configured', 'summary' => 'Manage posting rules, approvals, numbering sequences, and period locks.'],
                ],
                'recordsTitle' => 'Configuration Checklist',
                'records' => [
                    ['item' => 'Fiscal Year 2026', 'owner' => 'Finance', 'amount' => '12 periods', 'status' => 'Open'],
                    ['item' => 'VAT Standard Rate', 'owner' => 'Tax', 'amount' => '15%', 'status' => 'Active'],
                    ['item' => 'Journal Approval Rule', 'owner' => 'Controller', 'amount' => 'Above ZWL 5,000', 'status' => 'Active'],
                ],
            ],
            'ai-automation' => [
                'metrics' => [
                    ['label' => 'Forecasts', 'value' => AiForecast::count(), 'icon' => 'cpu', 'colour' => 'col-blue'],
                    ['label' => 'In Review', 'value' => AiForecast::where('status', 'review')->count(), 'icon' => 'eye', 'colour' => 'col-orange'],
                    ['label' => 'Approved', 'value' => AiForecast::where('status', 'approved')->count(), 'icon' => 'check-circle', 'colour' => 'col-green'],
                    ['label' => 'Avg Confidence', 'value' => number_format((float) AiForecast::avg('confidence'), 1).'%', 'icon' => 'activity', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Auto-Coding', 'status' => 'Human Review', 'summary' => 'Recommend GL accounts and tax codes with confidence scores before posting.'],
                    ['title' => 'Forecasting', 'status' => 'Modelled', 'summary' => 'Maintain cash-flow, revenue, inventory, and expense forecast scenarios.'],
                    ['title' => 'Monitoring', 'status' => 'Controlled', 'summary' => 'Track confidence, drift, review status, and audit evidence for every prediction.'],
                ],
                'recordsTitle' => 'AI Forecast & Auto-Coding Queue',
            ],
            'client-portal' => [
                'metrics' => [
                    ['label' => 'Portal Users', 'value' => PortalAccess::count(), 'icon' => 'globe', 'colour' => 'col-blue'],
                    ['label' => 'Active Access', 'value' => PortalAccess::where('status', true)->count(), 'icon' => 'unlock', 'colour' => 'col-green'],
                    ['label' => 'Expiring', 'value' => PortalAccess::whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(14))->count(), 'icon' => 'clock', 'colour' => 'col-orange'],
                    ['label' => 'Customers Linked', 'value' => PortalAccess::whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'), 'icon' => 'users', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Public Access', 'status' => 'Tokenised', 'summary' => 'Issue expiring portal tokens tied to customers and permissions.'],
                    ['title' => 'Customer Documents', 'status' => 'Ready', 'summary' => 'Expose invoices, statements, receipts, and shared files through the portal.'],
                    ['title' => 'Requests', 'status' => 'Routed', 'summary' => 'Capture service and billing requests from customers without back-office login.'],
                ],
                'recordsTitle' => 'Portal Access List',
            ],
            'revenue-recognition' => [
                'metrics' => [
                    ['label' => 'Contracts', 'value' => RevenueContract::count(), 'icon' => 'trending-up', 'colour' => 'col-blue'],
                    ['label' => 'Active', 'value' => RevenueContract::where('status', 'active')->count(), 'icon' => 'check-circle', 'colour' => 'col-green'],
                    ['label' => 'Transaction Price', 'value' => 'ZWL '.number_format((float) RevenueContract::sum('transaction_price'), 2), 'icon' => 'dollar-sign', 'colour' => 'col-purple'],
                    ['label' => 'Obligations', 'value' => RevenueContract::sum('performance_obligations'), 'icon' => 'list', 'colour' => 'col-orange'],
                ],
                'workstreams' => [
                    ['title' => 'Contract Identification', 'status' => 'ASC 606', 'summary' => 'Capture customer contracts, transaction price, dates, and recognition method.'],
                    ['title' => 'Performance Obligations', 'status' => 'Allocated', 'summary' => 'Track obligations and pricing foundations for revenue allocation.'],
                    ['title' => 'Recognition Schedules', 'status' => 'Journal Ready', 'summary' => 'Prepare deferred revenue and recognition basis for GL posting.'],
                ],
                'recordsTitle' => 'Revenue Contracts',
            ],
            'einvoicing-tax-mesh' => [
                'metrics' => [
                    ['label' => 'Documents', 'value' => EinvoiceDocument::count(), 'icon' => 'send', 'colour' => 'col-blue'],
                    ['label' => 'Submitted', 'value' => EinvoiceDocument::where('status', 'submitted')->count(), 'icon' => 'upload', 'colour' => 'col-orange'],
                    ['label' => 'Cleared', 'value' => EinvoiceDocument::where('status', 'cleared')->count(), 'icon' => 'check-circle', 'colour' => 'col-green'],
                    ['label' => 'Tax Total', 'value' => 'ZWL '.number_format((float) EinvoiceDocument::sum('tax_amount'), 2), 'icon' => 'percent', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Jurisdiction Mesh', 'status' => 'Configured', 'summary' => 'Route documents by country, network, authority, and clearance mandate.'],
                    ['title' => 'Validation', 'status' => 'Preflight', 'summary' => 'Check taxable amounts, tax, payload metadata, and authority references.'],
                    ['title' => 'Lifecycle Events', 'status' => 'Webhook Ready', 'summary' => 'Ingest submitted, cleared, rejected, cancelled, and payment-linked events.'],
                ],
                'recordsTitle' => 'E-Invoice Clearance Queue',
            ],
            'esg-carbon' => [
                'metrics' => [
                    ['label' => 'Activities', 'value' => CarbonActivity::count(), 'icon' => 'activity', 'colour' => 'col-blue'],
                    ['label' => 'Verified', 'value' => CarbonActivity::where('status', 'verified')->count(), 'icon' => 'check-circle', 'colour' => 'col-green'],
                    ['label' => 'Reported', 'value' => CarbonActivity::where('status', 'reported')->count(), 'icon' => 'file-text', 'colour' => 'col-orange'],
                    ['label' => 'Total CO2e', 'value' => number_format((float) CarbonActivity::sum('co2e'), 4), 'icon' => 'bar-chart-2', 'colour' => 'col-purple'],
                ],
                'workstreams' => [
                    ['title' => 'Activity Capture', 'status' => 'Operational', 'summary' => 'Record emissions activity by scope, category, quantity, and evidence.'],
                    ['title' => 'CO2e Calculation', 'status' => 'Calculated', 'summary' => 'Calculate carbon equivalent from activity quantity and emission factor.'],
                    ['title' => 'ESG Reporting', 'status' => 'Auditable', 'summary' => 'Promote verified activities into reported ESG datasets.'],
                ],
                'recordsTitle' => 'Carbon Activity Register',
            ],
            default => [
                'metrics' => [],
                'workstreams' => [],
                'recordsTitle' => 'Module Records',
                'records' => [],
            ],
        };
    }
}
