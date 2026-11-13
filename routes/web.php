<?php

use App\Http\Controllers\CompanyManagementController;
use App\Http\Controllers\CompanySetupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserManagementController;
use App\Modules\Accounting\Http\Controllers\AccountingDashboardController;
use App\Modules\Accounting\Http\Controllers\AccountingPeriodController;
use App\Modules\Accounting\Http\Controllers\AccountingSettingsController;
use App\Modules\Accounting\Http\Controllers\BankReconciliationController;
use App\Modules\Accounting\Http\Controllers\BudgetController;
use App\Modules\Accounting\Http\Controllers\DepreciationController;
use App\Modules\Accounting\Http\Controllers\ChartOfAccountController;
use App\Modules\Accounting\Http\Controllers\ClientPortalController;
use App\Modules\Accounting\Http\Controllers\CustomerController as AccountingCustomerController;
use App\Modules\Accounting\Http\Controllers\CustomerReceiptController;
use App\Modules\Accounting\Http\Controllers\CreditNoteController;
use App\Modules\Accounting\Http\Controllers\EnterpriseRecordController;
use App\Modules\Accounting\Http\Controllers\FinancialStatementController;
use App\Modules\Accounting\Http\Controllers\FiscalYearController;
use App\Modules\Accounting\Http\Controllers\ItemController;
use App\Modules\Accounting\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Http\Controllers\ModuleController;
use App\Modules\Accounting\Http\Controllers\PayablesAgingController;
use App\Modules\Accounting\Http\Controllers\PaymentRunController;
use App\Modules\Accounting\Http\Controllers\PosSaleController;
use App\Modules\Accounting\Http\Controllers\PurchaseOrderController;
use App\Modules\Accounting\Http\Controllers\ReceivablesAgingController;
use App\Modules\Accounting\Http\Controllers\SalesInvoiceController;
use App\Modules\Accounting\Http\Controllers\SalesOrderController;
use App\Modules\Accounting\Http\Controllers\StatutoryReportController;
use App\Modules\Accounting\Http\Controllers\StoresRequisitionController;
use App\Modules\Accounting\Http\Controllers\SupplierController as AccountingSupplierController;
use App\Modules\Accounting\Http\Controllers\SupplierInvoiceController;
use App\Modules\Accounting\Http\Controllers\SupplierPaymentController;
use App\Modules\Accounting\Http\Controllers\TransferOrderController;
use App\Modules\Accounting\Http\Controllers\TrialBalanceController;
use App\Modules\Accounting\Http\Controllers\InventoryOverviewController;
use App\Modules\Accounting\Http\Controllers\WarehouseController;
use App\Modules\Accounting\Http\Controllers\WebhookEventController;

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Auth;

// Public website
Route::controller(\App\Http\Controllers\SiteController::class)->name('site.')->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/services', 'services')->name('services');
    Route::get('/how-we-work', 'how')->name('how');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'send')->middleware('throttle:5,10')->name('contact.send');
});
Route::get('/client/login', [ClientPortalController::class, 'loginForm'])->name('portal.login');
Route::post('/client/login', [ClientPortalController::class, 'login'])->middleware('throttle:portal-login')->name('portal.login.submit');
Route::get('/client/password/change', [ClientPortalController::class, 'changePasswordForm'])->name('portal.password.change');
Route::post('/client/password/change', [ClientPortalController::class, 'changePassword'])->name('portal.password.update');
Route::get('/client/dashboard', [ClientPortalController::class, 'dashboard'])->name('portal.dashboard');
Route::post('/client/logout', [ClientPortalController::class, 'logout'])->name('portal.logout');
Route::get('/client/invoices/{invoice}/pdf', [ClientPortalController::class, 'invoicePdf'])->name('portal.invoices.pdf');
Route::post('/client/invoices/{invoice}/payments', [ClientPortalController::class, 'payment'])->name('portal.payments.store');
Route::get('/portal/{token}', [ClientPortalController::class, 'show'])->name('portal.show');

// Employee self-service portal (separate from staff accounts; see EmployeePortalSession).
Route::prefix('staff')->name('staff.')->controller(\App\Modules\Payroll\Http\Controllers\EmployeePortalController::class)->group(function () {
    Route::get('/login', 'loginForm')->name('login');
    Route::post('/login', 'login')->middleware('throttle:staff-login')->name('login.submit');
    Route::post('/logout', 'logout')->name('logout');
    Route::middleware('staff.portal')->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/password', 'passwordForm')->name('password.edit');
        Route::post('/password', 'updatePassword')->name('password.update');
        Route::get('/payslips', 'payslips')->name('payslips');
        Route::get('/payslips/{run}', 'payslipPdf')->whereNumber('run')->name('payslips.pdf');
        Route::get('/leave', 'leave')->name('leave');
        Route::post('/leave', 'storeLeave')->name('leave.store');
        Route::post('/leave/{leave}/cancel', 'cancelLeave')->whereNumber('leave')->name('leave.cancel');
        Route::get('/overtime', 'overtime')->name('overtime');
        Route::post('/overtime', 'storeOvertime')->name('overtime.store');
        Route::post('/overtime/{overtime}/cancel', 'cancelOvertime')->whereNumber('overtime')->name('overtime.cancel');
    });
});
Route::post('/webhooks/{source}', [WebhookEventController::class, 'store'])->name('webhooks.store');

Auth::routes();


// Company onboarding wizard (company admin) — reachable before setup is complete.
Route::middleware(['auth', 'permission'])->prefix('company')->group(function () {
    Route::controller(CompanySetupController::class)->prefix('setup')->name('company.setup.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::get('/currencies', 'currencies')->name('currencies');
        Route::post('/currencies', 'storeCurrencies')->name('currencies.store');
        Route::get('/tax', 'tax')->name('tax');
        Route::post('/tax', 'storeTax')->name('tax.store');
        Route::get('/ledger', 'ledger')->name('ledger');
        Route::post('/ledger', 'storeLedger')->name('ledger.store');
    });
    Route::view('/pending', 'company.pending')->name('company.pending');
});

// Every route below is checked against config/permissions.php by the `permission` middleware.
// Subscription billing (company admin). Reachable while the workspace is locked.
Route::post('/billing/webhooks/{provider}', [\App\Http\Controllers\Billing\BillingController::class, 'webhook'])->name('billing.webhooks');
Route::middleware(['auth', 'company.setup', 'permission'])->prefix('billing')->name('billing.')
    ->controller(\App\Http\Controllers\Billing\BillingController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/locked', 'locked')->name('locked');
        Route::post('/checkout', 'checkout')->name('checkout');
        Route::get('/invoices/{invoice}', 'invoice')->name('invoices.show');
        Route::post('/invoices/{invoice}/pay', 'pay')->name('invoices.pay');
        Route::get('/payments/{payment}/return', 'returned')->name('payments.return');
        Route::get('/sandbox/{payment}', 'sandbox')->name('sandbox.show');
        Route::post('/sandbox/{payment}', 'sandboxComplete')->name('sandbox.complete');
    });

// Help Centre: open to every signed-in user, including during setup or while billing is locked.
Route::middleware('auth')->prefix('help')->name('help.')->controller(\App\Http\Controllers\HelpController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/manual.pdf', 'manual')->name('manual');
    Route::get('/setup-checklist.pdf', 'checklist')->name('checklist');
    Route::get('/templates/{key}', 'template')->name('template');
    Route::get('/{slug}', 'show')->name('show');
    Route::get('/{slug}/pdf', 'pdf')->name('pdf');
});

Route::middleware(['auth', 'company.setup', 'subscription', 'permission', 'plan.feature', 'approval.rules'])->group(function () {
    Route::get('/dashboard', AccountingDashboardController::class)->name('dashboard');

    Route::resource('/company/team', TeamController::class)
        ->parameters(['team' => 'user'])
        ->except(['show'])
        ->names('company.team');

    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
        Route::resource('/modules/{module}/records', EnterpriseRecordController::class)
            ->parameters(['records' => 'record'])
            ->only(['store', 'edit', 'update', 'destroy'])
            ->names('modules.records');

        Route::prefix('general-ledger')->name('gl.')->group(function () {
            Route::resource('fiscal-years', FiscalYearController::class)
                ->parameters(['fiscal-years' => 'fiscalYear'])
                ->except(['create', 'show']);
            Route::resource('periods', AccountingPeriodController::class)->except(['create', 'show']);
            Route::resource('chart-of-accounts', ChartOfAccountController::class)
                ->parameters(['chart-of-accounts' => 'account'])
                ->names('accounts')
                ->except(['create', 'show']);
            Route::resource('journals', JournalEntryController::class);
            Route::post('/journals/{journal}/post', [JournalEntryController::class, 'post'])->name('journals.post');
            Route::resource('budgets', BudgetController::class)->except(['create', 'show']);
            Route::get('/trial-balance', TrialBalanceController::class)->name('trial-balance');
            Route::get('/profit-and-loss', [FinancialStatementController::class, 'profitAndLoss'])->name('profit-and-loss');
            Route::get('/balance-sheet', [FinancialStatementController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/cash-flow', [FinancialStatementController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/statutory-reports', [StatutoryReportController::class, 'index'])->name('statutory.index');
            Route::get('/statutory-reports/{report}', [StatutoryReportController::class, 'download'])->name('statutory.download');
        });

        Route::prefix('accounts-receivable')->name('ar.')->group(function () {
            Route::resource('customers', AccountingCustomerController::class)->except(['create', 'show']);
            Route::post('/customers/{customer}/portal-password', [AccountingCustomerController::class, 'issuePortalPassword'])->name('customers.portal-password.issue');
            Route::delete('/customers/{customer}/portal-password', [AccountingCustomerController::class, 'revokePortalPassword'])->name('customers.portal-password.revoke');
            Route::resource('invoices', SalesInvoiceController::class);
            Route::get('/invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('invoices.pdf');
            Route::post('/invoices/{invoice}/post', [SalesInvoiceController::class, 'post'])->name('invoices.post');
            Route::post('/invoices/{invoice}/zimra', [SalesInvoiceController::class, 'submitToZimra'])->name('invoices.zimra');
            Route::resource('credit-notes', CreditNoteController::class)
                ->parameters(['credit-notes' => 'creditNote'])
                ->only(['index', 'store']);
            Route::post('/credit-notes/{creditNote}/post', [CreditNoteController::class, 'post'])->name('credit-notes.post');
            Route::resource('receipts', CustomerReceiptController::class)->only(['index', 'store']);
            Route::post('/receipts/{receipt}/post', [CustomerReceiptController::class, 'post'])->name('receipts.post');
            Route::get('/aging', ReceivablesAgingController::class)->name('aging.index');
        });

        Route::prefix('accounts-payable')->name('ap.')->group(function () {
            Route::resource('suppliers', AccountingSupplierController::class)->except(['create', 'show']);
            Route::resource('supplier-invoices', SupplierInvoiceController::class)
                ->parameters(['supplier-invoices' => 'supplierInvoice']);
            Route::get('/supplier-invoices/{supplierInvoice}/pdf', [SupplierInvoiceController::class, 'pdf'])->name('supplier-invoices.pdf');
            Route::post('/supplier-invoices/{supplierInvoice}/post', [SupplierInvoiceController::class, 'post'])->name('supplier-invoices.post');
            Route::resource('payments', SupplierPaymentController::class)->only(['index', 'store']);
            Route::post('/payments/{payment}/post', [SupplierPaymentController::class, 'post'])->name('payments.post');
            Route::resource('payment-runs', PaymentRunController::class)
                ->parameters(['payment-runs' => 'paymentRun'])
                ->only(['index', 'store', 'show']);
            Route::post('/payment-runs/{paymentRun}/post', [PaymentRunController::class, 'post'])->name('payment-runs.post');
            Route::get('/aging', PayablesAgingController::class)->name('aging.index');
        });

        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', InventoryOverviewController::class)->name('overview');
            Route::resource('items', ItemController::class)->except(['create', 'show']);
            Route::resource('warehouses', WarehouseController::class)->except(['create', 'show']);
            Route::resource('sales-orders', SalesOrderController::class)
                ->parameters(['sales-orders' => 'salesOrder']);
            Route::post('/sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
            Route::post('/sales-orders/{salesOrder}/fulfill', [SalesOrderController::class, 'fulfill'])->name('sales-orders.fulfill');
            Route::resource('purchase-orders', PurchaseOrderController::class)
                ->parameters(['purchase-orders' => 'purchaseOrder']);
            Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
            Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
            Route::resource('transfer-orders', TransferOrderController::class)
                ->parameters(['transfer-orders' => 'transferOrder'])
                ->only(['index', 'store', 'show']);
            Route::post('/transfer-orders/{transferOrder}/ship', [TransferOrderController::class, 'ship'])->name('transfer-orders.ship');
            Route::post('/transfer-orders/{transferOrder}/receive', [TransferOrderController::class, 'receive'])->name('transfer-orders.receive');
            Route::resource('stores-requisitions', StoresRequisitionController::class)
                ->parameters(['stores-requisitions' => 'requisition'])
                ->only(['index', 'store', 'show']);
            Route::post('/stores-requisitions/{requisition}/approve', [StoresRequisitionController::class, 'approve'])->name('stores-requisitions.approve');
            Route::post('/stores-requisitions/{requisition}/issue', [StoresRequisitionController::class, 'issue'])->name('stores-requisitions.issue');
        });

        Route::prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [PosSaleController::class, 'index'])->name('index');
            Route::get('/terminal', [PosSaleController::class, 'create'])->name('create');
            Route::post('/sales', [PosSaleController::class, 'store'])->name('store');
            Route::get('/sales/{sale}', [PosSaleController::class, 'show'])->name('show');
            Route::get('/sales/{sale}/receipt', [PosSaleController::class, 'receipt'])->name('receipt');
        });

        Route::prefix('banking')->name('banking.')->controller(BankReconciliationController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/accounts/{bankAccount}', 'linkAccount')->name('accounts.link');
            Route::post('/statements', 'import')->name('statements.store');
            Route::get('/statements/{statement}', 'show')->name('statements.show');
            Route::delete('/statements/{statement}', 'destroy')->name('statements.destroy');
            Route::post('/statements/{statement}/auto-match', 'autoMatch')->name('statements.auto-match');
            Route::post('/statements/{statement}/complete', 'complete')->name('statements.complete');
            Route::post('/lines/{line}/match', 'match')->name('lines.match');
            Route::delete('/lines/{line}/match', 'unmatch')->name('lines.unmatch');
            Route::post('/lines/{line}/journal', 'createEntry')->name('lines.journal');
        });
        Route::get('/fixed-assets', [ModuleController::class, 'fixedAssets'])->name('fixed-assets.index');
        Route::get('/fixed-assets/depreciation', [DepreciationController::class, 'index'])->name('fixed-assets.depreciation.index');
        Route::post('/fixed-assets/depreciation', [DepreciationController::class, 'store'])->name('fixed-assets.depreciation.store');
        Route::prefix('reporting')->name('reporting.')->controller(\App\Modules\Accounting\Http\Controllers\ReportingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/library', 'library')->name('library');
            Route::get('/run/{type}', 'run')->name('run');
            Route::post('/saved', 'storeSaved')->name('saved.store');
            Route::get('/saved/{report}', 'openSaved')->name('saved.show');
            Route::delete('/saved/{report}', 'destroySaved')->name('saved.destroy');
        });
        Route::get('/crm', [ModuleController::class, 'crm'])->name('crm.index');
        Route::prefix('bom-job-costing')->name('bom-job-costing.')->controller(\App\Modules\Accounting\Http\Controllers\BomJobCostingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/boms', 'boms')->name('boms.index');
            Route::get('/boms/create', 'createBom')->name('boms.create');
            Route::post('/boms', 'storeBom')->name('boms.store');
            Route::get('/boms/{bom}', 'showBom')->name('boms.show');
            Route::get('/boms/{bom}/edit', 'editBom')->name('boms.edit');
            Route::put('/boms/{bom}', 'updateBom')->name('boms.update');
            Route::get('/work-orders', 'workOrders')->name('work-orders.index');
            Route::post('/work-orders', 'storeWorkOrder')->name('work-orders.store');
            Route::get('/work-orders/{workOrder}', 'showWorkOrder')->name('work-orders.show');
            Route::post('/work-orders/{workOrder}/release', 'releaseWorkOrder')->name('work-orders.release');
            Route::post('/work-orders/{workOrder}/issue', 'issueWorkOrder')->name('work-orders.issue');
            Route::post('/work-orders/{workOrder}/complete', 'completeWorkOrder')->name('work-orders.complete');
            Route::post('/work-orders/{workOrder}/cancel', 'cancelWorkOrder')->name('work-orders.cancel');
            Route::get('/jobs', 'jobs')->name('jobs.index');
            Route::get('/jobs/create', 'createJob')->name('jobs.create');
            Route::post('/jobs', 'storeJob')->name('jobs.store');
            Route::get('/jobs/{job}', 'showJob')->name('jobs.show');
            Route::get('/jobs/{job}/edit', 'editJob')->name('jobs.edit');
            Route::put('/jobs/{job}', 'updateJob')->name('jobs.update');
            Route::post('/jobs/{job}/entries', 'storeEntry')->name('jobs.entries.store');
            Route::post('/jobs/{job}/invoice', 'invoiceJob')->name('jobs.invoice');
            Route::post('/jobs/{job}/close', 'closeJob')->name('jobs.close');
            Route::post('/jobs/{job}/acknowledge', 'acknowledgeJob')->name('jobs.acknowledge');
        });
        Route::prefix('annuity-billing')->name('annuity-billing.')->controller(\App\Modules\Accounting\Http\Controllers\AnnuityBillingController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/contracts/create', 'create')->name('contracts.create');
            Route::post('/contracts', 'store')->name('contracts.store');
            Route::get('/contracts/{contract}', 'show')->name('contracts.show');
            Route::get('/contracts/{contract}/edit', 'edit')->name('contracts.edit');
            Route::put('/contracts/{contract}', 'update')->name('contracts.update');
            Route::post('/contracts/{contract}/pause', 'pause')->name('contracts.pause');
            Route::post('/contracts/{contract}/resume', 'resume')->name('contracts.resume');
            Route::post('/contracts/{contract}/cancel', 'cancel')->name('contracts.cancel');
            Route::get('/runs', 'preview')->name('runs.preview');
            Route::post('/runs', 'runBilling')->name('runs.store');
            Route::get('/runs/{run}', 'showRun')->name('runs.show');
        });
        Route::prefix('payroll')->name('payroll.')->controller(\App\Modules\Payroll\Http\Controllers\PayrollController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/employees', 'employees')->name('employees.index');
            Route::get('/employees/create', 'createEmployee')->name('employees.create');
            Route::post('/employees', 'storeEmployee')->name('employees.store');
            Route::get('/employees/{employee}', 'showEmployee')->name('employees.show');
            Route::get('/employees/{employee}/edit', 'editEmployee')->name('employees.edit');
            Route::put('/employees/{employee}', 'updateEmployee')->name('employees.update');
            Route::post('/employees/{employee}/items', 'storeEmployeeItem')->name('employees.items.store');
            Route::delete('/employees/{employee}/items/{item}', 'destroyEmployeeItem')->name('employees.items.destroy');
            Route::get('/pay-items', 'items')->name('items.index');
            Route::post('/pay-items', 'storeItem')->name('items.store');
            Route::put('/pay-items/{item}', 'updateItem')->name('items.update');
            Route::get('/statutory-settings', 'settings')->name('settings.index');
            Route::post('/statutory-settings', 'saveSettings')->name('settings.store');
            Route::get('/runs', 'runs')->name('runs.index');
            Route::post('/runs', 'storeRun')->name('runs.store');
            Route::get('/runs/{run}', 'showRun')->name('runs.show');
            Route::post('/runs/{run}/inputs', 'storeInput')->name('runs.inputs.store');
            Route::delete('/runs/{run}/inputs/{input}', 'destroyInput')->name('runs.inputs.destroy');
            Route::post('/runs/{run}/calculate', 'calculate')->name('runs.calculate');
            Route::post('/runs/{run}/approve', 'approve')->name('runs.approve');
            Route::post('/runs/{run}/pay', 'markPaid')->name('runs.pay');
            Route::post('/runs/{run}/cancel', 'cancel')->name('runs.cancel');
            Route::get('/runs/{run}/payslips/{employee}', 'payslip')->name('runs.payslip');
            Route::get('/runs/{run}/export/{report}', 'export')->whereIn('report', ['register', 'paye', 'nssa', 'bank'])->name('runs.export');
        });
        Route::prefix('payroll')->name('payroll.')->controller(\App\Modules\Payroll\Http\Controllers\PayrollRequestsController::class)->group(function () {
            Route::get('/requests', 'index')->name('requests.index');
            Route::post('/requests/leave/{leave}', 'decideLeave')->name('requests.leave.decide');
            Route::post('/requests/overtime/{overtime}', 'decideOvertime')->name('requests.overtime.decide');
            Route::post('/leave-types', 'storeLeaveType')->name('leave-types.store');
            Route::put('/leave-types/{type}', 'updateLeaveType')->name('leave-types.update');
            Route::post('/employees/{employee}/portal-access', 'issuePortalAccess')->name('employees.portal.issue');
            Route::delete('/employees/{employee}/portal-access', 'revokePortalAccess')->name('employees.portal.revoke');
        });
        Route::prefix('approvals-audit')->name('approvals-audit.')->controller(\App\Modules\Accounting\Http\Controllers\ApprovalsAuditController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/{type}/{id}/reject', 'reject')->whereNumber('id')->name('reject');
            Route::get('/rules', 'rules')->name('rules.index');
            Route::post('/rules', 'storeRule')->name('rules.store');
            Route::put('/rules/{rule}', 'updateRule')->name('rules.update');
            Route::delete('/rules/{rule}', 'destroyRule')->name('rules.destroy');
            Route::get('/audit-trail', 'auditLog')->name('audit.index');
            Route::get('/audit-trail/export', 'auditExport')->name('audit.export');
            Route::get('/audit-trail/{log}', 'auditShow')->whereNumber('log')->name('audit.show');
        });
        Route::prefix('ai-automation')->name('ai-automation.')->controller(\App\Modules\Accounting\Http\Controllers\AiAutomationController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/forecasts', 'forecasts')->name('forecasts.index');
            Route::post('/forecasts', 'generateForecasts')->name('forecasts.generate');
            Route::post('/forecasts/review', 'reviewForecasts')->name('forecasts.review');
            Route::get('/collections', 'collections')->name('collections');
            Route::get('/insights', 'insights')->name('insights.index');
            Route::post('/insights/scan', 'runDetection')->name('insights.run');
            Route::post('/insights/{insight}/review', 'reviewInsight')->name('insights.review');
            Route::get('/auto-coding', 'autoCoding')->name('auto-coding');
            Route::get('/suggest-account', 'suggest')->middleware('throttle:120,1')->name('suggest');
        });
        Route::get('/client-portal', [ModuleController::class, 'clientPortal'])->name('client-portal.index');
        Route::get('/revenue-recognition', [ModuleController::class, 'revenueRecognition'])->name('revenue-recognition.index');
        Route::get('/einvoicing-tax-mesh', [ModuleController::class, 'einvoicingTaxMesh'])->name('einvoicing-tax-mesh.index');
        Route::get('/esg-carbon', [ModuleController::class, 'esgCarbon'])->name('esg-carbon.index');
        Route::get('/settings', [AccountingSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/currencies', [AccountingSettingsController::class, 'storeCurrency'])->name('settings.currencies.store');
        Route::put('/settings/currencies/{currency}', [AccountingSettingsController::class, 'updateCurrency'])->name('settings.currencies.update');
        Route::delete('/settings/currencies/{currency}', [AccountingSettingsController::class, 'destroyCurrency'])->name('settings.currencies.destroy');
        Route::put('/settings/account-mappings', [AccountingSettingsController::class, 'updateMappings'])->name('settings.mappings.update');
        Route::post('/settings/exchange-rates', [AccountingSettingsController::class, 'storeRate'])->name('settings.rates.store');
        Route::delete('/settings/exchange-rates/{rate}', [AccountingSettingsController::class, 'destroyRate'])->name('settings.rates.destroy');
        Route::post('/settings/tax-codes', [AccountingSettingsController::class, 'storeTaxCode'])->name('settings.tax-codes.store');
        Route::put('/settings/tax-codes/{taxCode}', [AccountingSettingsController::class, 'updateTaxCode'])->name('settings.tax-codes.update');
        Route::delete('/settings/tax-codes/{taxCode}', [AccountingSettingsController::class, 'destroyTaxCode'])->name('settings.tax-codes.destroy');
    });
});

Route::middleware(['auth', 'superuser'])->prefix('system')->name('system.')->group(function () {
    Route::get('/platform', \App\Http\Controllers\Platform\PlatformDashboardController::class)->name('platform.dashboard');
    Route::controller(\App\Http\Controllers\Platform\TenantController::class)->prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{company}', 'show')->name('show');
        Route::post('/{company}/plan', 'changePlan')->name('plan');
        Route::post('/{company}/promotion', 'grantPromotion')->name('promotion');
        Route::post('/{company}/trial', 'extendTrial')->name('trial');
        Route::post('/{company}/suspend', 'suspend')->name('suspend');
        Route::post('/{company}/reactivate', 'reactivate')->name('reactivate');
        Route::post('/{company}/cancel', 'cancel')->name('cancel');
        Route::post('/{company}/invoices/{invoice}/payments', 'recordPayment')->name('payments.record');
    });
    Route::get('/enquiries', [\App\Http\Controllers\Platform\EnquiryController::class, 'index'])->name('enquiries.index');
    Route::post('/enquiries/{enquiry}/handled', [\App\Http\Controllers\Platform\EnquiryController::class, 'handled'])->name('enquiries.handled');
    Route::controller(\App\Http\Controllers\Platform\PlatformBillingController::class)->group(function () {
        Route::get('/plans', 'plans')->name('plans.index');
        Route::put('/plans/{plan}', 'updatePlan')->name('plans.update');
        Route::get('/coupons', 'coupons')->name('coupons.index');
        Route::post('/coupons', 'storeCoupon')->name('coupons.store');
        Route::post('/coupons/{coupon}/toggle', 'toggleCoupon')->name('coupons.toggle');
        Route::get('/gateways', 'gateways')->name('gateways.index');
        Route::put('/gateways/{gateway}', 'updateGateway')->name('gateways.update');
    });
    Route::resource('companies', CompanyManagementController::class)->except('show');
    Route::resource('users', UserManagementController::class)->except('show');
});
