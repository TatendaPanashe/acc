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

Route::middleware(['auth', 'company.setup', 'subscription', 'permission', 'plan.feature'])->group(function () {
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
        Route::get('/reporting', [ModuleController::class, 'reporting'])->name('reporting.index');
        Route::get('/crm', [ModuleController::class, 'crm'])->name('crm.index');
        Route::get('/bom-job-costing', [ModuleController::class, 'bomJobCosting'])->name('bom-job-costing.index');
        Route::get('/annuity-billing', [ModuleController::class, 'annuityBilling'])->name('annuity-billing.index');
        Route::get('/approvals-audit', [ModuleController::class, 'approvalsAudit'])->name('approvals-audit.index');
        Route::get('/ai-automation', [ModuleController::class, 'aiAutomation'])->name('ai-automation.index');
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
